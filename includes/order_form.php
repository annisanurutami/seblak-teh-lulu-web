<?php

declare(strict_types=1);

$orderFormProducts = $menuProducts ?? active_products();
$orderFormPrasmananOptions = $orderFormPrasmananOptions ?? active_prasmanan_options();
$hasPrasmanan = !empty($orderFormPrasmananOptions['toppings']);
$orderFormAvailable = (bool) $orderFormProducts || $hasPrasmanan;
$orderFormItems = [];
foreach ($orderFormProducts as $product) {
    $categoryName = strtolower((string) ($product['nama_kategori'] ?? ''));
    $product['menu_group'] = str_contains($categoryName, 'minuman')
        ? 'minuman'
        : (str_contains($categoryName, 'seblak') ? 'seblak' : 'menu-lainnya');
    $product['options'] = ['variants' => [], 'toppings' => []];
    $orderFormItems[] = $product;
}
?>
<?php if (!$orderFormAvailable): ?>
    <?php if (!empty($isPublicMenu)): ?>
        <div class="store-empty">
            <span class="store-empty-icon" aria-hidden="true">✳</span>
            <h3>Menu sedang disiapkan</h3>
            <p>Menu hangatnya segera hadir. Coba mampir lagi sebentar, ya.</p>
        </div>
    <?php else: ?>
        <div class="empty-state">Belum ada produk aktif. Tambahkan produk dari menu Produk.</div>
    <?php endif; ?>
<?php else: ?>
    <div class="order-menu-toolbar" data-order-menu-toolbar>
        <label class="list-search">
            <span>Cari menu</span>
            <input class="list-search-input" type="search" data-order-menu-search placeholder="Cari nama atau kategori menu" aria-label="Cari menu">
        </label>
        <div class="order-menu-category">
            <span>Kategori</span>
            <select data-order-menu-category aria-label="Pilih kategori menu">
                <option value="seblak">Seblak</option>
                <option value="menu-lainnya">Menu lainnya</option>
                <option value="minuman">Minuman</option>
            </select>
        </div>
    </div>
    <?php if ($hasPrasmanan): ?>
        <button class="button button-outline seblak-add-button" type="button" data-add-seblak-bowl>+ Tambah mangkuk Seblak</button>
    <?php endif; ?>
    <div class="product-grid">
        <?php if ($hasPrasmanan): ?>
            <template data-seblak-bowl-template>
                <section class="seblak-bowl" data-seblak-bowl data-order-menu-item data-menu-category="seblak">
                    <div class="seblak-bowl-heading">
                        <div class="seblak-bowl-title">
                            <h3>Seblak</h3>
                            <label class="seblak-bowl-name">
                                <span>Nama</span>
                                <input type="text" name="items[prasmanan][bowls][__INDEX__][name]" maxlength="80" placeholder="Contoh: Yota">
                            </label>
                        </div>
                        <button class="button button-small button-danger-outline" type="button" data-remove-seblak-bowl>Hapus mangkuk</button>
                    </div>
                    <input type="hidden" name="items[prasmanan][bowls][__INDEX__][quantity]" value="0" data-prasmanan-quantity>
                    <?php if (!empty($orderFormPrasmananOptions['variants'])): ?>
                        <div class="prasmanan-variant-options" data-prasmanan-variants>
                            <?php foreach ($orderFormPrasmananOptions['variants'] as $group): ?>
                                <?php $choices = db_all('SELECT id, nama_pilihan, harga_tambahan FROM variant_options WHERE variant_group_id = :id AND status = 1 ORDER BY nama_pilihan', ['id' => $group['id']]); ?>
                                <?php $requiresEggTopping = is_egg_related_name((string) $group['nama_varian']); ?>
                                <fieldset class="choice-group" data-required-variant="<?= $group['wajib_dipilih'] ? '1' : '0' ?>" <?= $requiresEggTopping ? 'data-requires-topping="telur"' : '' ?>>
                                    <legend><?= e($group['nama_varian']) ?><?= $group['wajib_dipilih'] ? ' *' : '' ?></legend>
                                    <?php if ($requiresEggTopping): ?><small class="variant-dependency-hint" data-variant-dependency-hint>Pilih topping telur terlebih dahulu.</small><?php endif; ?>
                                    <?php if ($group['tipe_pilihan'] === 'satu'): ?>
                                        <select name="items[prasmanan][bowls][__INDEX__][variants][<?= (int) $group['id'] ?>]">
                                            <option value="">Pilih opsi</option>
                                            <?php foreach ($choices as $choice): ?>
                                                <option value="<?= (int) $choice['id'] ?>" data-extra="<?= e((string) $choice['harga_tambahan']) ?>"><?= e($choice['nama_pilihan']) ?><?= (float) $choice['harga_tambahan'] > 0 ? ' (+' . e(rupiah($choice['harga_tambahan'])) . ')' : '' ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <div class="choice-list">
                                            <?php foreach ($choices as $choice): ?>
                                                <label class="inline-choice">
                                                    <input type="checkbox" name="items[prasmanan][bowls][__INDEX__][variants][<?= (int) $group['id'] ?>][]" value="<?= (int) $choice['id'] ?>" data-extra="<?= e((string) $choice['harga_tambahan']) ?>">
                                                    <?= e($choice['nama_pilihan']) ?><?= (float) $choice['harga_tambahan'] > 0 ? ' (+' . e(rupiah($choice['harga_tambahan'])) . ')' : '' ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </fieldset>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="topping-grid">
                        <?php foreach ($orderFormPrasmananOptions['toppings'] as $topping): ?>
                            <article class="product-card topping-product-card" data-prasmanan-topping-card>
                                <?php if ($topping['gambar']): ?>
                                    <img class="product-image" src="<?= e(app_base_path() . '/' . ltrim($topping['gambar'], '/')) ?>" alt="<?= e($topping['nama_topping']) ?>">
                                <?php else: ?>
                                    <div class="product-placeholder topping-placeholder" aria-hidden="true"><?= e(strtoupper(substr($topping['nama_topping'], 0, 2))) ?></div>
                                <?php endif; ?>
                                <div class="product-card-body">
                                    <div class="product-card-heading">
                                        <h3><?= e($topping['nama_topping']) ?></h3>
                                        <strong class="price"><?= e(rupiah($topping['harga'])) ?></strong>
                                    </div>
                                    <div class="topping-product-details">
                                        <span><?= (int) $topping['pcs_per_paket'] ?> pcs</span>
                                        <div class="quantity-stepper" role="group" aria-label="Jumlah <?= e($topping['nama_topping']) ?>">
                                            <button class="quantity-step-button" type="button" data-quantity-adjust="-1" aria-label="Kurangi <?= e($topping['nama_topping']) ?>" disabled>-</button>
                                            <output class="quantity-step-value" aria-live="polite">0</output>
                                            <input class="quantity-step-input" type="hidden" name="items[prasmanan][bowls][__INDEX__][toppings][<?= (int) $topping['id'] ?>]" value="0" data-quantity-min="0" data-quantity-max="20" data-topping-price="<?= e((string) $topping['harga']) ?>" data-pcs-per-unit="<?= (int) $topping['pcs_per_paket'] ?>">
                                            <button class="quantity-step-button" type="button" data-quantity-adjust="1" aria-label="Tambah <?= e($topping['nama_topping']) ?>">+</button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </template>
        <?php endif; ?>
        <?php foreach ($orderFormItems as $product): ?>
            <?php $itemKey = (string) (int) $product['id']; ?>
            <?php $options = $product['options']; ?>
            <article class="product-card" data-order-menu-item data-product-price="<?= e((string) $product['harga']) ?>" data-menu-category="<?= e($product['menu_group']) ?>">
                <?php if ($product['gambar']): ?>
                    <img class="product-image" src="<?= e(app_base_path() . '/' . ltrim($product['gambar'], '/')) ?>" alt="<?= e($product['nama_produk']) ?>">
                <?php else: ?>
                    <div class="product-placeholder" aria-hidden="true">STL</div>
                <?php endif; ?>
                <div class="product-card-body">
                    <div class="product-card-heading">
                        <div>
                            <span class="category-caption"><?= e($product['nama_kategori'] ?? 'Tanpa kategori') ?></span>
                            <h3><?= e($product['nama_produk']) ?></h3>
                        </div>
                        <strong class="price"><?= e(rupiah($product['harga'])) ?></strong>
                    </div>
                    <?php foreach ($options['variants'] as $group): ?>
                        <?php $choices = db_all('SELECT id, nama_pilihan, harga_tambahan FROM variant_options WHERE variant_group_id = :id AND status = 1 ORDER BY nama_pilihan', ['id' => $group['id']]); ?>
                        <fieldset class="choice-group" data-required-variant="<?= $group['wajib_dipilih'] ? '1' : '0' ?>">
                            <legend><?= e($group['nama_varian']) ?><?= $group['wajib_dipilih'] ? ' *' : '' ?></legend>
                            <?php if ($group['tipe_pilihan'] === 'satu'): ?>
                                <select name="items[<?= e($itemKey) ?>][variants][<?= (int) $group['id'] ?>]">
                                    <option value="">Pilih opsi</option>
                                    <?php foreach ($choices as $choice): ?>
                                        <option value="<?= (int) $choice['id'] ?>" data-extra="<?= e((string) $choice['harga_tambahan']) ?>"><?= e($choice['nama_pilihan']) ?><?= (float) $choice['harga_tambahan'] > 0 ? ' (+' . e(rupiah($choice['harga_tambahan'])) . ')' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="choice-list">
                                    <?php foreach ($choices as $choice): ?>
                                        <label class="inline-choice">
                                            <input type="checkbox" name="items[<?= e($itemKey) ?>][variants][<?= (int) $group['id'] ?>][]" value="<?= (int) $choice['id'] ?>" data-extra="<?= e((string) $choice['harga_tambahan']) ?>">
                                            <?= e($choice['nama_pilihan']) ?><?= (float) $choice['harga_tambahan'] > 0 ? ' (+' . e(rupiah($choice['harga_tambahan'])) . ')' : '' ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </fieldset>
                    <?php endforeach; ?>

                    <div class="product-quantity-field">
                        <span>Jumlah</span>
                        <span class="quantity-stepper" role="group" aria-label="Jumlah <?= e($product['nama_produk']) ?>">
                            <button class="quantity-step-button" type="button" data-quantity-adjust="-1" aria-label="Kurangi jumlah <?= e($product['nama_produk']) ?>" disabled>-</button>
                            <output class="quantity-step-value" aria-live="polite">0</output>
                            <input class="quantity-step-input" type="hidden" name="items[<?= e($itemKey) ?>][quantity]" value="0" data-quantity-min="0" data-quantity-max="50">
                            <button class="quantity-step-button" type="button" data-quantity-adjust="1" aria-label="Tambah jumlah <?= e($product['nama_produk']) ?>">+</button>
                        </span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="order-total-bar">
        <div class="order-summary" data-order-summary hidden>
            <strong class="order-summary-heading">Pesanan dipilih</strong>
            <ul class="order-summary-items" data-order-summary-items></ul>
        </div>
        <div class="order-total-row">
            <span>Total</span>
            <strong data-order-total>Rp 0</strong>
        </div>
    </div>
<?php endif; ?>
