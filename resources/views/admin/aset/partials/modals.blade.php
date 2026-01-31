<div class="modal fade" id="inputDataModal" data-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Input Data Aset Baru</h4>
                <button type="button" class="close cancel-draw" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="formAset">
                    <input type="hidden" id="geometryData" name="geometry">
                    
                    <div class="form-group">
                        <label>Nama Aset <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="namaAset" required placeholder="Contoh: Tanah Wakaf">
                    </div>
                    
                    <div class="form-group">
                        <label>Jenis Aset <span class="text-danger">*</span></label>
                        <select class="form-control" id="jenisAset" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Tanah">Tanah</option>
                            <option value="Bangunan">Bangunan</option>
                            <option value="Jalan">Jalan / Infrastruktur</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea class="form-control" id="ketAset" rows="3" placeholder="Deskripsi tambahan..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Simpan Data
                    </button>
                </form>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default cancel-draw" data-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>