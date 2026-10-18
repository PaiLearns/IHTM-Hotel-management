<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

function redirect_with_inventory_message($type, $message, $old = array())
{
    $_SESSION['inventory_flash'] = array(
        'type' => $type,
        'message' => $message,
    );
    $_SESSION['inventory_form_old'] = $old;
    header('Location: inventory.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inventory.php');
    exit;
}

$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    redirect_with_inventory_message('error', 'Your request could not be verified. Reload the page and try again.');
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
if (!in_array($action, array('add', 'edit', 'archive'), true)) {
    redirect_with_inventory_message('error', 'Select a valid inventory action.');
}

function inventory_positive_or_zero($value)
{
    if (!is_string($value) && !is_int($value)) {
        return false;
    }
    $number = filter_var($value, FILTER_VALIDATE_INT);
    return $number !== false && $number >= 0 && $number <= 2147483647 ? $number : false;
}

function inventory_valid_date($value)
{
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : false;
}

$itemId = isset($_POST['item_id']) ? filter_var($_POST['item_id'], FILTER_VALIDATE_INT) : false;
if ($action !== 'add' && ($itemId === false || $itemId < 1)) {
    redirect_with_inventory_message('error', 'Select a valid inventory item.');
}

if ($action === 'archive') {
    try {
        $pdo->beginTransaction();
        $itemStatement = $pdo->prepare(
            'SELECT id FROM inventory_items WHERE id = :item_id AND is_active = 1 AND archived_at IS NULL FOR UPDATE'
        );
        $itemStatement->execute(array('item_id' => $itemId));
        if (!$itemStatement->fetch()) {
            $pdo->rollBack();
            redirect_with_inventory_message('error', 'That inventory item could not be found or is already archived.');
        }

        $stockStatement = $pdo->prepare(
            'SELECT id, room_id, quantity, item_condition, status
             FROM inventory_stock
             WHERE item_id = :item_id AND archived_at IS NULL
             FOR UPDATE'
        );
        $stockStatement->execute(array('item_id' => $itemId));
        $stockRows = $stockStatement->fetchAll();
        $movementStatement = $pdo->prepare(
            'INSERT INTO inventory_movements
                (item_id, action, quantity, from_room_id, from_room_number, from_condition, from_status, notes, changed_by)
             VALUES
                (:item_id, :action, :quantity, :room_id, :room_number, :item_condition, :status, :notes, :changed_by)'
        );
        foreach ($stockRows as $stock) {
            $roomNumber = null;
            if ($stock['room_id'] !== null) {
                $roomStatement = $pdo->prepare('SELECT room_number FROM rooms WHERE id = :room_id');
                $roomStatement->execute(array('room_id' => $stock['room_id']));
                $roomNumber = $roomStatement->fetchColumn();
            }
            $movementStatement->execute(array(
                'item_id' => $itemId,
                'action' => 'archived',
                'quantity' => $stock['quantity'],
                'room_id' => $stock['room_id'],
                'room_number' => $roomNumber === false ? null : $roomNumber,
                'item_condition' => $stock['item_condition'],
                'status' => $stock['status'],
                'notes' => 'Inventory item archived.',
                'changed_by' => (int) $_SESSION['user_id'],
            ));
        }

        $archiveStock = $pdo->prepare(
            'UPDATE inventory_stock
             SET archived_at = CURRENT_TIMESTAMP
             WHERE item_id = :item_id AND archived_at IS NULL'
        );
        $archiveStock->execute(array('item_id' => $itemId));
        $archiveItem = $pdo->prepare(
            'UPDATE inventory_items
             SET is_active = 0, archived_at = CURRENT_TIMESTAMP
             WHERE id = :item_id'
        );
        $archiveItem->execute(array('item_id' => $itemId));
        $pdo->commit();
        redirect_with_inventory_message('success', 'Inventory item archived. Its records have been retained.');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('IHTM inventory archive failed: ' . $exception->getMessage());
        redirect_with_inventory_message('error', 'The inventory item could not be archived. Please try again.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('IHTM inventory archive failed: ' . $exception->getMessage());
        redirect_with_inventory_message('error', 'The inventory item could not be archived. Please try again.');
    }
}

$name = trim(isset($_POST['name']) ? (string) $_POST['name'] : '');
$categoryId = isset($_POST['category_id']) ? filter_var($_POST['category_id'], FILTER_VALIDATE_INT) : false;
$sku = trim(isset($_POST['sku']) ? (string) $_POST['sku'] : '');
$unit = trim(isset($_POST['unit']) ? (string) $_POST['unit'] : '');
$description = trim(isset($_POST['description']) ? (string) $_POST['description'] : '');
$stockNotes = trim(isset($_POST['stock_notes']) ? (string) $_POST['stock_notes'] : '');
$quantity = inventory_positive_or_zero(isset($_POST['quantity']) ? $_POST['quantity'] : null);
$reorderLevel = inventory_positive_or_zero(isset($_POST['reorder_level']) ? $_POST['reorder_level'] : null);
$itemCondition = isset($_POST['item_condition']) ? (string) $_POST['item_condition'] : '';
$status = isset($_POST['status']) ? (string) $_POST['status'] : '';
$purchaseDate = inventory_valid_date(isset($_POST['purchase_date']) ? (string) $_POST['purchase_date'] : '');
$old = array(
    'name' => $name,
    'category_id' => $categoryId === false ? '' : (string) $categoryId,
    'sku' => $sku,
    'unit' => $unit,
    'quantity' => isset($_POST['quantity']) ? (string) $_POST['quantity'] : '',
    'reorder_level' => isset($_POST['reorder_level']) ? (string) $_POST['reorder_level'] : '',
    'item_condition' => $itemCondition,
    'status' => $status,
    'purchase_date' => isset($_POST['purchase_date']) ? (string) $_POST['purchase_date'] : '',
    'description' => $description,
);

if ($name === '' || strlen($name) > 120 || $unit === '' || strlen($unit) > 30) {
    redirect_with_inventory_message('error', 'Enter an item name and unit within their character limits.', $old);
}
if ($categoryId === false || $categoryId < 1) {
    redirect_with_inventory_message('error', 'Select a valid item category.', $old);
}
if (strlen($sku) > 64 || strlen($description) > 65535 || strlen($stockNotes) > 65535 || $quantity === false || $reorderLevel === false) {
    redirect_with_inventory_message('error', 'Check the SKU, description, quantity, and reorder threshold.', $old);
}
if (!in_array($itemCondition, array('new', 'good', 'fair', 'damaged', 'needs_replacement'), true)) {
    redirect_with_inventory_message('error', 'Select a valid item condition.', $old);
}
if (!in_array($status, array('available', 'in_use', 'needs_attention', 'missing', 'unavailable'), true)) {
    redirect_with_inventory_message('error', 'Select a valid inventory status.', $old);
}
if ($purchaseDate === false) {
    redirect_with_inventory_message('error', 'Enter a valid purchase date.', $old);
}

try {
    $pdo->beginTransaction();

    $categoryStatement = $pdo->prepare(
        'SELECT id FROM inventory_categories WHERE id = :category_id AND is_active = 1 FOR UPDATE'
    );
    $categoryStatement->execute(array('category_id' => $categoryId));
    if (!$categoryStatement->fetch()) {
        $pdo->rollBack();
        redirect_with_inventory_message('error', 'The selected category is unavailable.', $old);
    }

    if ($action === 'add') {
        $itemStatement = $pdo->prepare(
            'INSERT INTO inventory_items (category_id, sku, name, unit, reorder_level, description)
             VALUES (:category_id, :sku, :name, :unit, :reorder_level, :description)'
        );
        $itemStatement->execute(array(
            'category_id' => $categoryId,
            'sku' => $sku !== '' ? $sku : null,
            'name' => $name,
            'unit' => $unit,
            'reorder_level' => $reorderLevel,
            'description' => $description !== '' ? $description : null,
        ));
        $itemId = (int) $pdo->lastInsertId();

        $stockStatement = $pdo->prepare(
            'INSERT INTO inventory_stock (item_id, room_id, quantity, item_condition, status, purchase_date, last_checked_at)
             VALUES (:item_id, NULL, :quantity, :item_condition, :status, :purchase_date, CURRENT_TIMESTAMP)'
        );
        $stockStatement->execute(array(
            'item_id' => $itemId,
            'quantity' => $quantity,
            'item_condition' => $itemCondition,
            'status' => $status,
            'purchase_date' => $purchaseDate,
        ));

        $movementStatement = $pdo->prepare(
            'INSERT INTO inventory_movements (item_id, action, quantity, to_condition, to_status, notes, changed_by)
             VALUES (:item_id, :action, :quantity, :item_condition, :status, :notes, :changed_by)'
        );
        $movementStatement->execute(array(
            'item_id' => $itemId,
            'action' => 'received',
            'quantity' => $quantity,
            'item_condition' => $itemCondition,
            'status' => $status,
            'notes' => 'Initial stock added to general storage.',
            'changed_by' => (int) $_SESSION['user_id'],
        ));
        $pdo->commit();
        redirect_with_inventory_message('success', 'Inventory item added to general storage.');
    }

    $stockId = isset($_POST['stock_id']) ? filter_var($_POST['stock_id'], FILTER_VALIDATE_INT) : false;
    if ($stockId === false || $stockId < 1) {
        $pdo->rollBack();
        redirect_with_inventory_message('error', 'Select a valid stock record.', $old);
    }

    $lockItem = $pdo->prepare(
        'SELECT id FROM inventory_items
         WHERE id = :item_id AND is_active = 1 AND archived_at IS NULL
         FOR UPDATE'
    );
    $lockItem->execute(array('item_id' => $itemId));
    if (!$lockItem->fetch()) {
        $pdo->rollBack();
        redirect_with_inventory_message('error', 'That inventory item could not be found or is archived.', $old);
    }

    $lockStock = $pdo->prepare(
        'SELECT id, quantity, item_condition, status
         FROM inventory_stock
         WHERE id = :stock_id AND item_id = :item_id AND archived_at IS NULL
         FOR UPDATE'
    );
    $lockStock->execute(array('stock_id' => $stockId, 'item_id' => $itemId));
    $currentStock = $lockStock->fetch();
    if (!$currentStock) {
        $pdo->rollBack();
        redirect_with_inventory_message('error', 'That stock record could not be found.', $old);
    }

    $updateItem = $pdo->prepare(
        'UPDATE inventory_items
         SET category_id = :category_id,
             sku = :sku,
             name = :name,
             unit = :unit,
             reorder_level = :reorder_level,
             description = :description
         WHERE id = :item_id'
    );
    $updateItem->execute(array(
        'category_id' => $categoryId,
        'sku' => $sku !== '' ? $sku : null,
        'name' => $name,
        'unit' => $unit,
        'reorder_level' => $reorderLevel,
        'description' => $description !== '' ? $description : null,
        'item_id' => $itemId,
    ));

    $updateStock = $pdo->prepare(
        'UPDATE inventory_stock
         SET quantity = :quantity,
             item_condition = :item_condition,
             status = :status,
             notes = :notes,
             purchase_date = :purchase_date,
             last_checked_at = CURRENT_TIMESTAMP
         WHERE id = :stock_id'
    );
    $updateStock->execute(array(
        'quantity' => $quantity,
        'item_condition' => $itemCondition,
        'status' => $status,
        'notes' => $stockNotes !== '' ? $stockNotes : null,
        'purchase_date' => $purchaseDate,
        'stock_id' => $stockId,
    ));

    $quantityChanged = (int) $currentStock['quantity'] !== $quantity;
    $conditionChanged = $currentStock['item_condition'] !== $itemCondition;
    $statusChanged = $currentStock['status'] !== $status;
    if ($quantityChanged || $conditionChanged || $statusChanged) {
        $movementAction = $quantityChanged
            ? 'quantity_adjustment'
            : ($conditionChanged ? 'condition_update' : 'status_update');
        $movementNotes = 'Stock details updated.';
        if ($quantityChanged) {
            $movementNotes .= ' Quantity changed from ' . (int) $currentStock['quantity'] . ' to ' . $quantity . '.';
        }
        if ($conditionChanged) {
            $movementNotes .= ' Condition changed from ' . $currentStock['item_condition'] . ' to ' . $itemCondition . '.';
        }
        if ($statusChanged) {
            $movementNotes .= ' Status changed from ' . $currentStock['status'] . ' to ' . $status . '.';
        }
        $movementStatement = $pdo->prepare(
            'INSERT INTO inventory_movements
                (item_id, action, quantity, from_condition, to_condition, from_status, to_status, notes, changed_by)
             VALUES
                (:item_id, :action, :quantity, :from_condition, :to_condition, :from_status, :to_status, :notes, :changed_by)'
        );
        $movementStatement->execute(array(
            'item_id' => $itemId,
            'action' => $movementAction,
            'quantity' => $quantityChanged ? abs($quantity - (int) $currentStock['quantity']) : $quantity,
            'from_condition' => $currentStock['item_condition'],
            'to_condition' => $itemCondition,
            'from_status' => $currentStock['status'],
            'to_status' => $status,
            'notes' => $movementNotes,
            'changed_by' => (int) $_SESSION['user_id'],
        ));
    }

    $pdo->commit();
    redirect_with_inventory_message('success', 'Inventory item and stock details updated.');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($exception->getCode() === '23000') {
        redirect_with_inventory_message('error', 'That SKU is already in use. Choose another SKU.', $old);
    }
    error_log('IHTM inventory save failed: ' . $exception->getMessage());
    redirect_with_inventory_message('error', 'The inventory item could not be saved. Please try again.', $old);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('IHTM inventory save failed: ' . $exception->getMessage());
    redirect_with_inventory_message('error', 'The inventory item could not be saved. Please try again.', $old);
}
