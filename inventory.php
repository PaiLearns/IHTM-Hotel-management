<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Inventory';
$categoryFilter = isset($_GET['category']) ? filter_var($_GET['category'], FILTER_VALIDATE_INT) : false;
$roomFilter = isset($_GET['room']) ? (string) $_GET['room'] : 'all';
$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : 'all';
$allowedStatuses = array('all', 'available', 'in_use', 'needs_attention', 'missing', 'unavailable');
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}
if ($roomFilter !== 'all' && $roomFilter !== 'storage' && (!ctype_digit($roomFilter) || (int) $roomFilter < 1)) {
    $roomFilter = 'all';
}

try {
    $categoryStatement = $pdo->query(
        'SELECT id, name FROM inventory_categories WHERE is_active = 1 ORDER BY name'
    );
    $categories = $categoryStatement->fetchAll();

    $roomStatement = $pdo->query('SELECT id, room_number FROM rooms ORDER BY room_number');
    $rooms = $roomStatement->fetchAll();

    $sql = 'SELECT
                item.id AS item_id,
                item.category_id,
                item.sku,
                item.name,
                item.unit,
                item.reorder_level,
                item.description,
                stock.id AS stock_id,
                stock.room_id,
                stock.quantity,
                stock.item_condition,
                stock.status,
                stock.notes AS stock_notes,
                stock.purchase_date,
                category.name AS category_name,
                room.room_number
            FROM inventory_items item
            INNER JOIN inventory_categories category ON category.id = item.category_id
            LEFT JOIN inventory_stock stock
                ON stock.item_id = item.id AND stock.archived_at IS NULL
            LEFT JOIN rooms room ON room.id = stock.room_id
            WHERE item.is_active = 1 AND item.archived_at IS NULL';
    $parameters = array();

    if ($categoryFilter !== false && $categoryFilter > 0) {
        $sql .= ' AND item.category_id = :category_id';
        $parameters['category_id'] = $categoryFilter;
    } else {
        $categoryFilter = false;
    }
    if ($roomFilter === 'storage') {
        $sql .= ' AND stock.room_id IS NULL';
    } elseif ($roomFilter !== 'all') {
        $sql .= ' AND stock.room_id = :room_id';
        $parameters['room_id'] = (int) $roomFilter;
    }
    if ($statusFilter !== 'all') {
        $sql .= ' AND stock.status = :status';
        $parameters['status'] = $statusFilter;
    }
    $sql .= ' ORDER BY category.name, item.name, stock.room_id';

    $inventoryStatement = $pdo->prepare($sql);
    $inventoryStatement->execute($parameters);
    $inventoryRows = $inventoryStatement->fetchAll();
} catch (PDOException $exception) {
    error_log('IHTM inventory listing failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Inventory could not be loaded. Check that the inventory schema has been applied and try again.');
}

$flash = isset($_SESSION['inventory_flash']) ? $_SESSION['inventory_flash'] : null;
unset($_SESSION['inventory_flash']);
$old = isset($_SESSION['inventory_form_old']) ? $_SESSION['inventory_form_old'] : array();
unset($_SESSION['inventory_form_old']);
$itemConditions = array(
    'new' => 'New',
    'good' => 'Good',
    'fair' => 'Fair',
    'damaged' => 'Damaged',
    'needs_replacement' => 'Needs replacement',
);
$stockStatuses = array(
    'available' => 'Available',
    'in_use' => 'In use',
    'needs_attention' => 'Needs attention',
    'missing' => 'Missing',
    'unavailable' => 'Unavailable',
);

require __DIR__ . '/includes/header.php';
?>
<section class="mb-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Hotel operations</p>
    <h1 class="mt-1 text-3xl font-bold tracking-tight text-ihtm-navy">Inventory</h1>
    <p class="mt-2 text-slate-600">Track hotel supplies, their quantities, condition, and current storage location.</p>
</section>

<?php if ($flash): ?>
    <div role="status" class="mb-5 rounded-lg border px-4 py-3 text-sm font-medium <?php echo $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>">
        <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<details class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm" open>
    <summary class="cursor-pointer px-5 py-4 text-lg font-bold text-ihtm-navy">Add inventory item</summary>
    <form action="inventory_actions.php" method="post" class="border-t border-slate-200 px-5 py-5">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="add">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="new-item-name" class="mb-1.5 block text-sm font-semibold text-slate-700">Item name</label>
                <input id="new-item-name" name="name" type="text" required maxlength="120" value="<?php echo htmlspecialchars(isset($old['name']) ? $old['name'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </div>
            <div>
                <label for="new-item-category" class="mb-1.5 block text-sm font-semibold text-slate-700">Category</label>
                <select id="new-item-category" name="category_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int) $category['id']; ?>" <?php echo isset($old['category_id']) && (int) $old['category_id'] === (int) $category['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="new-item-sku" class="mb-1.5 block text-sm font-semibold text-slate-700">SKU (optional)</label>
                <input id="new-item-sku" name="sku" type="text" maxlength="64" value="<?php echo htmlspecialchars(isset($old['sku']) ? $old['sku'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </div>
            <div>
                <label for="new-item-unit" class="mb-1.5 block text-sm font-semibold text-slate-700">Unit</label>
                <input id="new-item-unit" name="unit" type="text" required maxlength="30" value="<?php echo htmlspecialchars(isset($old['unit']) ? $old['unit'] : 'piece', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </div>
            <div>
                <label for="new-item-quantity" class="mb-1.5 block text-sm font-semibold text-slate-700">Quantity in storage</label>
                <input id="new-item-quantity" name="quantity" type="number" required min="0" max="2147483647" value="<?php echo htmlspecialchars(isset($old['quantity']) ? $old['quantity'] : '0', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </div>
            <div>
                <label for="new-item-reorder-level" class="mb-1.5 block text-sm font-semibold text-slate-700">Low-stock threshold</label>
                <input id="new-item-reorder-level" name="reorder_level" type="number" required min="0" max="2147483647" value="<?php echo htmlspecialchars(isset($old['reorder_level']) ? $old['reorder_level'] : '0', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </div>
            <div>
                <label for="new-item-condition" class="mb-1.5 block text-sm font-semibold text-slate-700">Condition</label>
                <select id="new-item-condition" name="item_condition" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                    <?php foreach ($itemConditions as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo isset($old['item_condition']) && $old['item_condition'] === $value ? 'selected' : ($value === 'good' && !isset($old['item_condition']) ? 'selected' : ''); ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="new-item-status" class="mb-1.5 block text-sm font-semibold text-slate-700">Status</label>
                <select id="new-item-status" name="status" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                    <?php foreach ($stockStatuses as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo isset($old['status']) && $old['status'] === $value ? 'selected' : ($value === 'available' && !isset($old['status']) ? 'selected' : ''); ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="new-item-purchase-date" class="mb-1.5 block text-sm font-semibold text-slate-700">Purchase date (optional)</label>
                <input id="new-item-purchase-date" name="purchase_date" type="date" value="<?php echo htmlspecialchars(isset($old['purchase_date']) ? $old['purchase_date'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <label for="new-item-description" class="mb-1.5 block text-sm font-semibold text-slate-700">Description / notes (optional)</label>
                <textarea id="new-item-description" name="description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"><?php echo htmlspecialchars(isset($old['description']) ? $old['description'] : '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>
        <button type="submit" class="mt-4 rounded-lg bg-ihtm-navy px-4 py-2.5 text-sm font-semibold text-white hover:bg-ihtm-blue">Add Item</button>
    </form>
</details>

<form action="inventory.php" method="get" class="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label for="filter-category" class="mb-1 block text-xs font-semibold text-slate-600">Category</label>
        <select id="filter-category" name="category" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo (int) $category['id']; ?>" <?php echo $categoryFilter === (int) $category['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="filter-room" class="mb-1 block text-xs font-semibold text-slate-600">Location</label>
        <select id="filter-room" name="room" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
            <option value="all" <?php echo $roomFilter === 'all' ? 'selected' : ''; ?>>All locations</option>
            <option value="storage" <?php echo $roomFilter === 'storage' ? 'selected' : ''; ?>>General storage</option>
            <?php foreach ($rooms as $room): ?>
                <option value="<?php echo (int) $room['id']; ?>" <?php echo $roomFilter === (string) $room['id'] ? 'selected' : ''; ?>>Room <?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="filter-status" class="mb-1 block text-xs font-semibold text-slate-600">Status</label>
        <select id="filter-status" name="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
            <?php foreach ($stockStatuses as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-end gap-2">
        <button type="submit" class="rounded-lg bg-ihtm-navy px-4 py-2 text-sm font-semibold text-white hover:bg-ihtm-blue">Filter</button>
        <a href="inventory.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Clear</a>
    </div>
</form>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-lg font-bold text-ihtm-navy">Inventory items</h2>
        <p class="mt-1 text-sm text-slate-500">Showing <?php echo count($inventoryRows); ?> stock record<?php echo count($inventoryRows) === 1 ? '' : 's'; ?>.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold">Item</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Category / SKU</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Location</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Quantity</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Condition</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!$inventoryRows): ?>
                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">No inventory matched these filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($inventoryRows as $row): ?>
                        <?php $lowStock = $row['quantity'] !== null && (int) $row['reorder_level'] > 0 && (int) $row['quantity'] <= (int) $row['reorder_level']; ?>
                        <tr class="align-top">
                            <td class="min-w-48 px-4 py-4">
                                <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if ($row['description'] !== null && $row['description'] !== ''): ?><p class="mt-1 max-w-xs text-xs text-slate-500"><?php echo htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                                <?php if ($lowStock): ?><span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Low stock</span><?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600">
                                <p><?php echo htmlspecialchars($row['category_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="mt-1 text-xs"><?php echo htmlspecialchars($row['sku'] !== null && $row['sku'] !== '' ? $row['sku'] : 'No SKU', ENT_QUOTES, 'UTF-8'); ?></p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600"><?php echo $row['room_id'] === null ? 'General storage' : 'Room ' . htmlspecialchars($row['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-4 py-4 font-medium text-slate-700"><?php echo $row['quantity'] === null ? 'No stock record' : (int) $row['quantity'] . ' ' . htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8'); ?><span class="block text-xs font-normal text-slate-500">Reorder at <?php echo (int) $row['reorder_level']; ?></span></td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600"><?php echo $row['item_condition'] === null ? '—' : htmlspecialchars(isset($itemConditions[$row['item_condition']]) ? $itemConditions[$row['item_condition']] : $row['item_condition'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600"><?php echo $row['status'] === null ? '—' : htmlspecialchars(isset($stockStatuses[$row['status']]) ? $stockStatuses[$row['status']] : $row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="min-w-44 px-4 py-3">
                                <details>
                                    <summary class="cursor-pointer text-sm font-semibold text-ihtm-blue hover:underline">Edit item / stock</summary>
                                    <?php if ($row['stock_id'] !== null): ?>
                                        <form action="inventory_actions.php" method="post" class="mt-3 min-w-64 space-y-3 rounded-lg bg-slate-50 p-3">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="item_id" value="<?php echo (int) $row['item_id']; ?>">
                                            <input type="hidden" name="stock_id" value="<?php echo (int) $row['stock_id']; ?>">
                                            <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-name-<?php echo (int) $row['stock_id']; ?>">Name</label><input id="edit-name-<?php echo (int) $row['stock_id']; ?>" name="name" type="text" required maxlength="120" value="<?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded border border-slate-300 px-2 py-1.5"></div>
                                            <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-category-<?php echo (int) $row['stock_id']; ?>">Category</label><select id="edit-category-<?php echo (int) $row['stock_id']; ?>" name="category_id" required class="w-full rounded border border-slate-300 bg-white px-2 py-1.5"><?php foreach ($categories as $category): ?><option value="<?php echo (int) $category['id']; ?>" <?php echo (int) $row['category_id'] === (int) $category['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                                            <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-sku-<?php echo (int) $row['stock_id']; ?>">SKU</label><input id="edit-sku-<?php echo (int) $row['stock_id']; ?>" name="sku" type="text" maxlength="64" value="<?php echo htmlspecialchars($row['sku'] === null ? '' : $row['sku'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded border border-slate-300 px-2 py-1.5"></div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-unit-<?php echo (int) $row['stock_id']; ?>">Unit</label><input id="edit-unit-<?php echo (int) $row['stock_id']; ?>" name="unit" type="text" required maxlength="30" value="<?php echo htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded border border-slate-300 px-2 py-1.5"></div>
                                                <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-quantity-<?php echo (int) $row['stock_id']; ?>">Quantity</label><input id="edit-quantity-<?php echo (int) $row['stock_id']; ?>" name="quantity" type="number" required min="0" max="2147483647" value="<?php echo (int) $row['quantity']; ?>" class="w-full rounded border border-slate-300 px-2 py-1.5"></div>
                                            </div>
                                            <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-reorder-<?php echo (int) $row['stock_id']; ?>">Low-stock threshold</label><input id="edit-reorder-<?php echo (int) $row['stock_id']; ?>" name="reorder_level" type="number" required min="0" max="2147483647" value="<?php echo (int) $row['reorder_level']; ?>" class="w-full rounded border border-slate-300 px-2 py-1.5"></div>
                                            <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-description-<?php echo (int) $row['stock_id']; ?>">Description</label><textarea id="edit-description-<?php echo (int) $row['stock_id']; ?>" name="description" rows="2" class="w-full rounded border border-slate-300 px-2 py-1.5"><?php echo htmlspecialchars($row['description'] === null ? '' : $row['description'], ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                                            <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-condition-<?php echo (int) $row['stock_id']; ?>">Condition</label><select id="edit-condition-<?php echo (int) $row['stock_id']; ?>" name="item_condition" required class="w-full rounded border border-slate-300 bg-white px-2 py-1.5"><?php foreach ($itemConditions as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $row['item_condition'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                                            <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-status-<?php echo (int) $row['stock_id']; ?>">Status</label><select id="edit-status-<?php echo (int) $row['stock_id']; ?>" name="status" required class="w-full rounded border border-slate-300 bg-white px-2 py-1.5"><?php foreach ($stockStatuses as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $row['status'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                                            <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-purchase-<?php echo (int) $row['stock_id']; ?>">Purchase date</label><input id="edit-purchase-<?php echo (int) $row['stock_id']; ?>" name="purchase_date" type="date" value="<?php echo htmlspecialchars($row['purchase_date'] === null ? '' : $row['purchase_date'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded border border-slate-300 px-2 py-1.5"></div>
                                            <div><label class="mb-1 block text-xs font-semibold text-slate-600" for="edit-stock-notes-<?php echo (int) $row['stock_id']; ?>">Stock notes</label><textarea id="edit-stock-notes-<?php echo (int) $row['stock_id']; ?>" name="stock_notes" rows="2" class="w-full rounded border border-slate-300 px-2 py-1.5"><?php echo htmlspecialchars($row['stock_notes'] === null ? '' : $row['stock_notes'], ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                                            <button type="submit" class="w-full rounded bg-ihtm-navy px-3 py-2 text-xs font-semibold text-white hover:bg-ihtm-blue">Save changes</button>
                                        </form>
                                    <?php else: ?>
                                        <p class="mt-2 text-xs text-slate-500">This item has no stock record to edit. Add stock from general storage.</p>
                                    <?php endif; ?>
                                </details>
                                <form action="inventory_actions.php" method="post" class="mt-2" onsubmit="return confirm('Archive this item and all its stock records? The history will be retained.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="archive">
                                    <input type="hidden" name="item_id" value="<?php echo (int) $row['item_id']; ?>">
                                    <button type="submit" class="text-xs font-semibold text-red-700 underline decoration-red-200 underline-offset-2 hover:text-red-900">Archive item</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
