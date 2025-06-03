<?php
// invoices/invoice_actions.php
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_login();
require_once __DIR__ . '/../includes/functions.php';

$redirect_url = base_url('invoices/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF). يرجى المحاولة مرة أخرى.", "danger");
        redirect($redirect_url);
    }

    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : '';
    $current_user_id = get_current_user_id();

    // --- Invoice Header Fields ---
    $invoice_id = isset($_POST['invoice_id']) && !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
    $invoice_number = isset($_POST['invoice_number']) ? sanitize_input($_POST['invoice_number']) : null;
    $invoice_sequence_number = isset($_POST['invoice_sequence_number']) && filter_var($_POST['invoice_sequence_number'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ? (int)$_POST['invoice_sequence_number'] : null;
    $invoice_date = isset($_POST['invoice_date']) ? sanitize_input($_POST['invoice_date']) : null;
    $invoice_time = isset($_POST['invoice_time']) ? sanitize_input($_POST['invoice_time']) : date('H:i:s');
    $due_date = isset($_POST['due_date']) ? sanitize_input($_POST['due_date']) : null;
    $invoice_type_zatca = isset($_POST['invoice_type_zatca']) ? sanitize_input($_POST['invoice_type_zatca']) : 'SimplifiedInvoice';
    $transaction_type_code = isset($_POST['transaction_type_code']) ? sanitize_input($_POST['transaction_type_code']) : '388';
    $invoice_status = isset($_POST['invoice_status']) ? sanitize_input($_POST['invoice_status']) : 'Unpaid';
    
    $lease_id_post = isset($_POST['lease_id']) && !empty($_POST['lease_id']) ? (int)$_POST['lease_id'] : null;
    $tenant_id_post = isset($_POST['tenant_id_invoice_direct']) && !empty($_POST['tenant_id_invoice_direct']) ? (int)$_POST['tenant_id_invoice_direct'] : null;

    // Determine tenant_id for the invoice
    // If lease_id is provided, fetch tenant_id from that lease
    $final_tenant_id = null;
    if ($lease_id_post) {
        $stmt_lease_tenant = $mysqli->prepare("SELECT tenant_id FROM leases WHERE id = ?");
        if ($stmt_lease_tenant) {
            $stmt_lease_tenant->bind_param("i", $lease_id_post);
            $stmt_lease_tenant->execute();
            $res_lease_tenant = $stmt_lease_tenant->get_result();
            if ($res_lease_tenant->num_rows > 0) {
                $final_tenant_id = $res_lease_tenant->fetch_assoc()['tenant_id'];
            }
            $stmt_lease_tenant->close();
        }
    }
    // If no tenant_id from lease, or no lease_id, use the directly selected tenant_id
    if (!$final_tenant_id && $tenant_id_post) {
        $final_tenant_id = $tenant_id_post;
    }


    $purchase_order_id = isset($_POST['purchase_order_id']) ? sanitize_input($_POST['purchase_order_id']) : null;
    $contract_id_invoice = isset($_POST['contract_id_invoice']) ? sanitize_input($_POST['contract_id_invoice']) : null; // Name from modal
    $invoice_description = isset($_POST['invoice_description']) ? sanitize_input($_POST['invoice_description']) : null;
    $zatca_notes = isset($_POST['zatca_notes']) ? sanitize_input($_POST['zatca_notes']) : null;

    $invoice_sub_total_amount_header = isset($_POST['invoice_sub_total_amount']) && filter_var($_POST['invoice_sub_total_amount'], FILTER_VALIDATE_FLOAT) ? (float)$_POST['invoice_sub_total_amount'] : 0.00; //This is calculated sum of items taxable amount
    $invoice_total_discount_header = isset($_POST['invoice_total_discount']) && filter_var($_POST['invoice_total_discount'], FILTER_VALIDATE_FLOAT) ? (float)$_POST['invoice_total_discount'] : 0.00;
    $invoice_vat_percentage_header = isset($_POST['invoice_vat_percentage_header']) && filter_var($_POST['invoice_vat_percentage_header'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 100]]) ? (float)$_POST['invoice_vat_percentage_header'] : (defined('VAT_PERCENTAGE') ? VAT_PERCENTAGE : 15.00);
    // invoice_total_vat_amount and invoice_total_amount are read-only in form, calculated by JS, but we will recalculate server-side from items
    $invoice_paid_amount_header = isset($_POST['invoice_paid_amount']) && filter_var($_POST['invoice_paid_amount'], FILTER_VALIDATE_FLOAT) ? (float)$_POST['invoice_paid_amount'] : 0.00;


    // --- Invoice Item Fields (Arrays) ---
    $item_names = isset($_POST['item_name']) ? $_POST['item_name'] : [];
    $item_quantities = isset($_POST['item_quantity']) ? $_POST['item_quantity'] : [];
    $item_unit_prices = isset($_POST['item_unit_price']) ? $_POST['item_unit_price'] : [];
    $item_vat_category_codes = isset($_POST['item_vat_category_code']) ? $_POST['item_vat_category_code'] : [];
    $item_vat_percentages = isset($_POST['item_vat_percentage']) ? $_POST['item_vat_percentage'] : [];
    $item_discounts = isset($_POST['item_discount_amount']) ? $_POST['item_discount_amount'] : [];


    // --- Basic Validations ---
    if (empty($invoice_number) || $invoice_sequence_number === null || empty($invoice_date) || empty($invoice_time) || empty($due_date) || $final_tenant_id === null) {
        set_message("الحقول الأساسية للفاتورة (رقم الفاتورة، رقم التسلسل، التاريخ، الوقت، تاريخ الاستحقاق، العميل) مطلوبة.", "danger");
        redirect($redirect_url . ($invoice_id ? '?edit=' . $invoice_id : ''));
    }
    if (count($item_names) === 0 || empty(array_filter($item_names))) { // Check if at least one item name is provided
        set_message("يجب إضافة بند واحد على الأقل للفاتورة.", "danger");
        redirect($redirect_url . ($invoice_id ? '?edit=' . $invoice_id : ''));
    }
     // Validate that all item arrays have the same count and basic item data is present
    $item_count = count($item_names);
    if ($item_count !== count($item_quantities) || $item_count !== count($item_unit_prices) || $item_count !== count($item_vat_category_codes) || $item_count !== count($item_vat_percentages) || $item_count !== count($item_discounts) ) {
        set_message("بيانات بنود الفاتورة غير متناسقة أو مفقودة.", "danger");
        redirect($redirect_url . ($invoice_id ? '?edit=' . $invoice_id : ''));
    }
    for($i=0; $i < $item_count; $i++){
        if(empty(trim($item_names[$i])) || !is_numeric($item_quantities[$i]) || !is_numeric($item_unit_prices[$i]) || !is_numeric($item_vat_percentages[$i]) || !is_numeric($item_discounts[$i])){
            set_message("أحد بنود الفاتورة يحتوي على بيانات غير صالحة أو مفقودة (الوصف، الكمية، السعر، نسبة الضريبة، الخصم).", "danger");
            redirect($redirect_url . ($invoice_id ? '?edit=' . $invoice_id : ''));
        }
    }


    // --- Server-side Calculation of Totals (to ensure integrity) ---
    $calculated_sub_total = 0; // Sum of (item_taxable_amount)
    $calculated_total_vat_on_items = 0; // Sum of (item_vat_amount)

    for ($i = 0; $i < $item_count; $i++) {
        $qty = (float)$item_quantities[$i];
        $price = (float)$item_unit_prices[$i];
        $item_disc = (float)$item_discounts[$i];
        $item_vat_rate = (float)$item_vat_percentages[$i];

        $item_line_subtotal_before_discount = $qty * $price;
        $item_taxable_for_item = $item_line_subtotal_before_discount - $item_disc;
        if ($item_taxable_for_item < 0) $item_taxable_for_item = 0;

        $item_vat_for_item = round(($item_taxable_for_item * $item_vat_rate) / 100, 2);
        
        $calculated_sub_total += $item_taxable_for_item; // This is sum of items' taxable amounts
        $calculated_total_vat_on_items += $item_vat_for_item;
    }

    // Apply invoice-level discount to the sum of item taxable amounts
    $invoice_net_amount_before_vat = $calculated_sub_total - $invoice_total_discount_header;
    if ($invoice_net_amount_before_vat < 0) $invoice_net_amount_before_vat = 0;

    // Vat for the invoice header (can be sum of item vats or calculated on net amount)
    // For ZATCA, sum of item VATs is more accurate if items have different rates.
    // If all items AND header have same VAT rate, $invoice_vat_percentage_header can be used on $invoice_net_amount_before_vat
    $final_invoice_vat = $calculated_total_vat_on_items;
    // If there was an invoice level discount, and we are to apply header VAT % on the discounted subtotal of items
    // (This logic depends on how the business wants to apply VAT on discounts)
    // For now, we use the sum of item VATs as calculated above ($calculated_total_vat_on_items)
    // $final_invoice_vat = round(($invoice_net_amount_before_vat * $invoice_vat_percentage_header) / 100, 2);
    
    $final_invoice_total = $invoice_net_amount_before_vat + $final_invoice_vat;


    // --- Begin Transaction ---
    $mysqli->begin_transaction();

    try {
        // --- Add Invoice Action ---
        if ($action === 'add_invoice') {
            // Check for duplicate invoice_number (internal) and invoice_sequence_number (ICV for ZATCA)
            $stmt_check_inv = $mysqli->prepare("SELECT id FROM invoices WHERE invoice_number = ? OR invoice_sequence_number = ?");
            $stmt_check_inv->bind_param("si", $invoice_number, $invoice_sequence_number);
            $stmt_check_inv->execute();
            $stmt_check_inv->store_result();
            if ($stmt_check_inv->num_rows > 0) {
                throw new Exception("رقم الفاتورة الداخلي أو رقم تسلسل الفاتورة (ICV) مستخدم بالفعل.");
            }
            $stmt_check_inv->close();

            $sql_invoice = "INSERT INTO invoices (lease_id, tenant_id, invoice_number, invoice_sequence_number, invoice_date, invoice_time, due_date, 
                                                invoice_type_zatca, transaction_type_code, notes_zatca, purchase_order_id, contract_id,
                                                sub_total_amount, discount_amount, vat_percentage, -- total_amount and vat_amount are generated
                                                paid_amount, status, created_by_id, zatca_status, previous_invoice_hash) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Not Sent', NULL)"; // PIH is NULL for now
            $stmt_invoice = $mysqli->prepare($sql_invoice);
            if (!$stmt_invoice) throw new Exception("خطأ في تجهيز استعلام إضافة الفاتورة: " . $mysqli->error);
            
            // Note: sub_total_amount in DB schema is sum of item_taxable_amounts.
            // discount_amount is invoice level discount.
            // vat_percentage is header level (used by generated column for total_amount if items don't have varied rates).
            $stmt_invoice->bind_param("iissssssssssdddsdi", 
                $lease_id_post, $final_tenant_id, $invoice_number, $invoice_sequence_number, $invoice_date, $invoice_time, $due_date,
                $invoice_type_zatca, $transaction_type_code, $zatca_notes, $purchase_order_id, $contract_id_invoice,
                $calculated_sub_total, $invoice_total_discount_header, $invoice_vat_percentage_header, // vat_percentage on invoice header
                $invoice_paid_amount_header, $invoice_status, $current_user_id
            );

            if (!$stmt_invoice->execute()) throw new Exception("خطأ في إضافة الفاتورة: " . $stmt_invoice->error);
            $invoice_id = $stmt_invoice->insert_id; // Get newly inserted invoice ID
            $stmt_invoice->close();

        // --- Edit Invoice Action ---
        } elseif ($action === 'edit_invoice') {
            if ($invoice_id === null) throw new Exception("معرف الفاتورة مفقود للتعديل.");

            // Check for duplicate invoice_number (internal) and invoice_sequence_number (ICV for ZATCA), excluding current invoice
            $stmt_check_inv_edit = $mysqli->prepare("SELECT id FROM invoices WHERE (invoice_number = ? OR invoice_sequence_number = ?) AND id != ?");
            $stmt_check_inv_edit->bind_param("sii", $invoice_number, $invoice_sequence_number, $invoice_id);
            $stmt_check_inv_edit->execute();
            $stmt_check_inv_edit->store_result();
            if ($stmt_check_inv_edit->num_rows > 0) {
                throw new Exception("رقم الفاتورة الداخلي أو رقم تسلسل الفاتورة (ICV) مستخدم بالفعل لفاتورة أخرى.");
            }
            $stmt_check_inv_edit->close();

            $sql_invoice = "UPDATE invoices SET 
                                lease_id = ?, tenant_id = ?, invoice_number = ?, invoice_sequence_number = ?, invoice_date = ?, invoice_time = ?, due_date = ?,
                                invoice_type_zatca = ?, transaction_type_code = ?, notes_zatca = ?, purchase_order_id = ?, contract_id = ?,
                                sub_total_amount = ?, discount_amount = ?, vat_percentage = ?,
                                paid_amount = ?, status = ? 
                            WHERE id = ?";
            $stmt_invoice = $mysqli->prepare($sql_invoice);
            if (!$stmt_invoice) throw new Exception("خطأ في تجهيز استعلام تعديل الفاتورة: " . $mysqli->error);

            $stmt_invoice->bind_param("iissssssssssdddsdi", 
                $lease_id_post, $final_tenant_id, $invoice_number, $invoice_sequence_number, $invoice_date, $invoice_time, $due_date,
                $invoice_type_zatca, $transaction_type_code, $zatca_notes, $purchase_order_id, $contract_id_invoice,
                $calculated_sub_total, $invoice_total_discount_header, $invoice_vat_percentage_header,
                $invoice_paid_amount_header, $invoice_status, $invoice_id
            );
            if (!$stmt_invoice->execute()) throw new Exception("خطأ في تعديل الفاتورة: " . $stmt_invoice->error);
            $stmt_invoice->close();

            // For edit, delete existing items first, then re-insert
            $stmt_delete_items = $mysqli->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            if (!$stmt_delete_items) throw new Exception("خطأ في تجهيز حذف البنود القديمة: " . $mysqli->error);
            $stmt_delete_items->bind_param("i", $invoice_id);
            if (!$stmt_delete_items->execute()) throw new Exception("خطأ في حذف البنود القديمة: " . $stmt_delete_items->error);
            $stmt_delete_items->close();
        } else {
            throw new Exception("الإجراء المطلوب غير معروف.");
        }

        // --- Insert Invoice Items ---
        $sql_item = "INSERT INTO invoice_items (invoice_id, item_name, quantity, unit_price_before_vat, item_discount_amount, item_vat_category_code, item_vat_percentage) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_item = $mysqli->prepare($sql_item);
        if (!$stmt_item) throw new Exception("خطأ في تجهيز استعلام إضافة بنود الفاتورة: " . $mysqli->error);

        for ($i = 0; $i < count($item_names); $i++) {
            $i_name = sanitize_input($item_names[$i]);
            $i_qty = (float)$item_quantities[$i];
            $i_price = (float)$item_unit_prices[$i];
            $i_vat_cat = sanitize_input($item_vat_category_codes[$i]);
            $i_vat_perc = (float)$item_vat_percentages[$i];
            $i_discount = (float)$item_discounts[$i];

            if (empty($i_name)) continue; // Skip empty item names

            $stmt_item->bind_param("isdddsd", $invoice_id, $i_name, $i_qty, $i_price, $i_discount, $i_vat_cat, $i_vat_perc);
            if (!$stmt_item->execute()) throw new Exception("خطأ في إضافة بند الفاتورة ('{$i_name}'): " . $stmt_item->error);
        }
        $stmt_item->close();

        $mysqli->commit(); // Commit transaction
        set_message("تمت معالجة الفاتورة بنجاح!", "success");

    } catch (Exception $e) {
        $mysqli->rollback(); // Rollback transaction on error
        set_message("خطأ: " . $e->getMessage(), "danger");
        error_log("Invoice Action Error: " . $e->getMessage() . " - POST Data: " . http_build_query($_POST));
    }

// --- Delete Invoice Action (GET request) ---
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_invoice') {
    if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
        set_message("خطأ في التحقق (CSRF) عند الحذف.", "danger");
        redirect($redirect_url);
    }

    $invoice_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($invoice_id_to_delete > 0) {
        // Check if invoice has related payments before allowing deletion, or handle payments (e.g. unlink)
        $stmt_check_payments = $mysqli->prepare("SELECT COUNT(*) as count FROM payments WHERE invoice_id = ?");
        $stmt_check_payments->bind_param("i", $invoice_id_to_delete);
        $stmt_check_payments->execute();
        $payments_count = $stmt_check_payments->get_result()->fetch_assoc()['count'];
        $stmt_check_payments->close();

        if ($payments_count > 0) {
            set_message("لا يمكن حذف هذه الفاتورة لوجود (" . $payments_count . ") دفعة/دفعات مرتبطة بها. يرجى حذف الدفعات أولاً أو إلغاء ربطها.", "warning");
        } else {
            // Deleting invoice will also delete its items due to ON DELETE CASCADE in DB schema
            $sql_delete = "DELETE FROM invoices WHERE id = ?";
            $stmt_delete = $mysqli->prepare($sql_delete);
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $invoice_id_to_delete);
                if ($stmt_delete->execute()) {
                    set_message("تم حذف الفاتورة وبنودها بنجاح!", "success");
                } else {
                    set_message("خطأ في حذف الفاتورة: " . $stmt_delete->error, "danger");
                    error_log("SQL Error Delete Invoice: " . $stmt_delete->error);
                }
                $stmt_delete->close();
            } else {
                set_message("خطأ في تجهيز استعلام حذف الفاتورة: " . $mysqli->error, "danger");
                error_log("SQL Prepare Error Delete Invoice: " . $mysqli->error);
            }
        }
    } else {
        set_message("معرف الفاتورة غير صحيح للحذف.", "danger");
    }
} else {
    set_message("طلب غير صالح أو طريقة وصول غير مدعومة.", "danger");
}

redirect($redirect_url);
?>