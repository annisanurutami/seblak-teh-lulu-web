<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_guard.php';

$edit = isset($_GET['edit']) ? db_one('SELECT * FROM variant_groups WHERE id = :id', ['id' => (int) $_GET['edit']]) : null;
$choices = $edit ? db_all('SELECT nama_pilihan, harga_tambahan FROM variant_options WHERE variant_group_id = :id ORDER BY id', ['id' => $edit['id']]) : [];
$choiceRows = $choices ?: [['nama_pilihan' => '', 'harga_tambahan' => '0']];
$groups = db_all(
    'SELECT vg.*, (SELECT COUNT(*) FROM variant_options vo WHERE vo.variant_group_id = vg.id) AS jumlah_pilihan
     FROM variant_groups vg ORDER BY vg.urutan, vg.nama_varian'
);
page_title('Katalog', 'Varian dinamis', 'Kelola pilihan varian untuk menu Seblak Prasmanan.');
?>
<div class="content-grid content-grid-aside">
    <section class="panel">
        <div class="panel-heading"><h2><?= $edit ? 'Ubah kelompok' : 'Kelompok varian baru' ?></h2></div>
        <form class="stack-form variant-form-grid" method="post" action="<?= e(app_base_path() . '/process.php') ?>">
            <?php csrf_field(); ?><input type="hidden" name="action" value="variant_save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <label>Nama kelompok<input name="nama_varian" maxlength="100" required value="<?= e($edit['nama_varian'] ?? '') ?>" placeholder="Contoh: Level pedas"></label>
            <label>Tipe pilihan<select name="tipe_pilihan"><option value="satu" <?= ($edit['tipe_pilihan'] ?? 'satu') === 'satu' ? 'selected' : '' ?>>Pilih satu</option><option value="banyak" <?= ($edit['tipe_pilihan'] ?? '') === 'banyak' ? 'selected' : '' ?>>Boleh pilih beberapa</option></select></label>
            <fieldset class="variant-choice-editor variant-form-wide" data-variant-choice-editor>
                <legend>Pilihan varian</legend>
                <p class="muted">Isi nama dan harga tambahan untuk setiap pilihan.</p>
                <div class="variant-choice-rows" data-variant-choice-rows>
                    <?php foreach ($choiceRows as $choice): ?>
                        <div class="variant-choice-row" data-variant-choice-row>
                            <label>Nama pilihan<input name="pilihan_nama[]" maxlength="100" required value="<?= e($choice['nama_pilihan']) ?>" placeholder="Contoh: Sedang"></label>
                            <label>Harga tambahan<input name="pilihan_harga[]" type="number" min="0" step="any" required value="<?= e((string) $choice['harga_tambahan']) ?>"></label>
                            <button class="button button-small button-danger-outline" type="button" data-remove-variant-choice aria-label="Hapus pilihan varian">Hapus</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-variant-choice-template>
                    <div class="variant-choice-row" data-variant-choice-row>
                        <label>Nama pilihan<input name="pilihan_nama[]" maxlength="100" required placeholder="Contoh: Sedang"></label>
                        <label>Harga tambahan<input name="pilihan_harga[]" type="number" min="0" step="any" required value="0"></label>
                        <button class="button button-small button-danger-outline" type="button" data-remove-variant-choice aria-label="Hapus pilihan varian">Hapus</button>
                    </div>
                </template>
                <button class="button button-outline" type="button" data-add-variant-choice>Tambah pilihan</button>
            </fieldset>
            <label>Urutan<input type="number" name="urutan" value="<?= (int) ($edit['urutan'] ?? 0) ?>"></label>
            <div class="variant-status-fields">
                <label class="check-line"><input type="checkbox" name="wajib_dipilih" value="1" <?= !isset($edit['wajib_dipilih']) || $edit['wajib_dipilih'] ? 'checked' : '' ?>> Wajib dipilih</label>
                <label class="check-line"><input type="checkbox" name="status" value="1" <?= !isset($edit['status']) || $edit['status'] ? 'checked' : '' ?>> Aktif</label>
            </div>
            <button class="button button-primary variant-form-wide" type="submit">Simpan varian</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><h2>Kelompok tersedia</h2></div>
        <div class="table-wrap"><table><thead><tr><th>Kelompok</th><th>Pilihan</th><th>Status</th><th></th></tr></thead><tbody>
        <?php if (!$groups) table_empty_row(4, 'Belum ada varian.'); ?>
        <?php foreach ($groups as $group): ?>
            <tr><td><strong><?= e($group['nama_varian']) ?></strong><small><?= $group['tipe_pilihan'] === 'satu' ? 'Pilih satu' : 'Pilih beberapa' ?><?= $group['wajib_dipilih'] ? ' · wajib' : '' ?></small></td><td><?= (int) $group['jumlah_pilihan'] ?></td><td><?= status_badge($group['status'] ? 'aktif' : 'nonaktif') ?></td><td><a class="button button-small button-outline" href="?page=variants&edit=<?= (int) $group['id'] ?>">Ubah</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
