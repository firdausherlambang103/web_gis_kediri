<div class="modal fade" id="modalImport" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-upload mr-2"></i>Import Data SHP</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="{{ route('asset.uploadShp') }}" method="POST" enctype="multipart/form-data" id="formImport">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle"></i> Pastikan file berformat <b>.zip</b> yang berisi (.shp, .shx, .dbf, .prj).
                    </div>
                    <div class="form-group">
                        <label>Pilih File ZIP</label>
                        <div class="custom-file">
                            <input type="file" name="shp_files[]" class="custom-file-input" id="customFile" multiple required accept=".zip">
                            <label class="custom-file-label" for="customFile">Choose file</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Upload & Proses</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="fas fa-edit mr-2"></i>Edit Data Aset</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="formEdit">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <input type="hidden" id="editId">
                    
                    <div class="form-group">
                        <label>Nama Aset <span class="text-danger">*</span></label>
                        <input type="text" id="editName" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipe Hak <span class="text-danger">*</span></label>
                                <select id="editStatus" class="form-control">
                                    <option value="Hak Milik">Hak Milik (HM)</option>
                                    <option value="Hak Pakai">Hak Pakai (HP)</option>
                                    <option value="Hak Guna Bangunan">HGB</option>
                                    <option value="Wakaf">Tanah Wakaf</option>
                                    <option value="Tanah Negara">Tanah Negara</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warna Peta</label>
                                <div class="input-group">
                                    <input type="color" id="editColor" class="form-control" style="height:38px">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kecamatan</label>
                                <input type="text" id="editKec" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Desa/Kelurahan</label>
                                <input type="text" id="editDesa" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Luas Tertulis (m²)</label>
                        <input type="number" step="0.01" id="editLuas" class="form-control">
                        <small class="text-muted">Biarkan jika tidak ingin mengubah perhitungan otomatis.</small>
                    </div>

                    <div class="form-group">
                        <label>Keterangan / Penggunaan</label>
                        <textarea id="editDesc" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>