<?php
global $app;

if (!$app) {
    exit();
}

class SSPDController extends Controller {

    /**
     * @var SSPDModel
     */
    public $model;

    /**
     * @var SSPDView
     */
    public $view;

    public function __construct() {
        $this->setType(self::TYPE_SINGLE);
        $this->setTitle('Surat Setoran');
        $this->setIcon('assignment_turned_in');

        $this->allowAccess('Administrator');
        $this->allowAccess('Penerimaan');
        $this->allowAccess('Pejabat');

        $this->addExceptionAccess('getdata', 'setdata', 'revdata', 'listdata');

        //Create model and view
        $this->prepare();
    }
    
    public function index() {
        
    }
    
    public function edit($id) {
        $this->view->edit($this->model->find($id));
    }
    
    public function save() {
        $this->model->save();
    }

    public function preview($id) {
        $this->view->preview($id);
    }

}

class SSPDModel extends Model {
    public $table = 'suratsetoran';
    public $primaryKey = 'ssID';

    public $ssID = 0;
	public $ssSkpID = 0;
    //public $ssBphID = 0;
    //public $ssBpkID = 0;
    public $ssTgl = '0000-00-00';
    public $ssNo = '';
    public $ssBulanPajak = 0;
    public $ssTahunPajak = 0;
    public $ssReklameBerdasarkanNo = '';
    public $ssReklameBerdasarkanAtau = '';
    public $ssRestoranNamaInstansi = '';
    public $ssRestoranNamaKegiatan = '';
    public $ssRestoranNoRekening = '';
    public $ssRestoranBelanja = '';
    public $ssRestoranNoPenunjukan = '';
    public $ssRestoranTglPenunjukan = '0000-00-00';
    public $ssRestoranDasarPenyetoranPersentase = 0;
    public $ssRestoranDasarPenyetoranNilai = 0;

    public $ssPembangkit = 0;

    public $ssKeterangan = '';
    public $ssJumlahPokok = 0;
    public $ssJumlahDenda = 0;
    
    //Diupdate oleh Bank
    //public $ssNamaSetor = '';
    //public $ssTglSetor = '0000-00-00';
    //public $ssJumlahSetorPokok = 0;
    //public $ssJumlahSetorDenda = 0;
    //public $ssStatusSetor = ''; //Belum Setor, Sudah Setor
    //public $ssWaktuSetor = '';

    /**
     * Mendapatkan sebuah record
     *
     * @param int $id
     *
     * @return ActionResult $actionResult Hasil aksi
     */
    public function find($id) {
        global $app;

        $actionResult = new ActionResult();

        if ($id > 0) {
            try {
                $this->selectRecord($id);
            } catch (ActionException $ex) {
                return $ex->getActionResult();
            }
        } else {
            $this->ssSkpID = $app->getRequestInt('id');
            $this->ssTgl = date('Y-m-d');
            $this->ssBulanPajak = $app->months[intval(date('m'))];
            $this->ssTahunPajak = date('Y');
        }

        $actionResult->value = $this;

        return $actionResult;
    }

    /**
     * Mendapatkan identifikasi data
     */
    public function getIdentification() {
        return "Data \"{$this->ssNo}\"";
    }

    /**
     * Menyimpan record
     *
     * @return ActionResult $actionResult Hasil aksi
     */
    public function save() {
        global $app;

        $this->bindRequest();

        //--Modify object (if necessary)
        $this->ssTgl = $app->NormalDateToMySQL($this->ssTgl);
        $this->ssRestoranTglPenunjukan = $app->NormalDateToMySQL($this->ssRestoranTglPenunjukan);
        $this->ssJumlahPokok = $app->MoneyToMySQL($_REQUEST['ssJumlahPokok']);
        $this->ssJumlahDenda = $app->MoneyToMySQL($_REQUEST['ssJumlahDenda']);

        //--Validate object (if necessary)
        $actionResult = new ActionResult();

        if (!$actionResult->hasErrorMessages()) {
            $excludedFields = array();

            $this->ssPembangkit = $_REQUEST['ssPembangkit'];
            $excludedFields[] = 'ssPembangkit';

            if ($this->ssID == 0) {
                $sql = "SELECT MAX(ssNo)
		                FROM suratsetoran
		                WHERE YEAR(ssTgl)='".substr($this->ssTgl,0,4)."'";
                $this->ssNo = $app->queryIntVal($sql) + 1;

                $this->ssStatusSetor = 'Belum Setor';
            } else {
                $excludedFields[] = 'ssStatusSetor';
            }

            $actionResultType = $this->saveRecordWithParams($excludedFields);
            $actionResult->setMessage($actionResultType, $this->getIdentification());

            $app->log(($actionResultType == ActionResult::INSERT_SUCCESSFUL ? "Menambah" : "Mengubah") . " data surat setoran dengan id " . $this->ssID . ", no " . $this->ssNo);
            
            header("Location:".$app->site."/admin/PenerimaanSSPD/index");
        } else {
            $actionResult->setMessage(ActionResult::SAVE_FAILED, $this->getIdentification());
            $actionResult->value = $this;
        }

        return $actionResult;
    }
}

class SSPDView extends View {
    /**
     * Menampilkan halaman ubah data
     *
     * @param ActionResult $actionResult Hasil aksi
     */
    public function edit($actionResult) {
        global $app;

        /* @var $obj SSPDModel */
        $obj = $actionResult->value;
        
        $sql = "SELECT *
                FROM skpd
                LEFT JOIN sptpd ON skpSptID=sptID
                LEFT JOIN pelayanan ON sptLyID=lyID
                LEFT JOIN jenispelayanan ON lyJlyID=jlyID
                LEFT JOIN obyek ON lyObyID=obyID
                LEFT JOIN wajibpajak ON lyWpID=wpID
                LEFT JOIN kelurahan ON wpKelID=kelID
                LEFT JOIN kecamatan ON kelKecID=kecID
                WHERE skpID='".$obj->ssSkpID."'";
        $objSKPD = $app->queryObject($sql);
?>
    <div class="container-fluid app-container">
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <form action="<?php echo $app->site; ?>/admin/<?php echo $app->act; ?>/save" method="post">
                    <input type="hidden" id="<?php echo $obj->primaryKey; ?>" name="<?php echo $obj->primaryKey; ?>" value="<?php echo $obj->{$obj->primaryKey}; ?>">
                    <input type="hidden" id="ssSkpID" name="ssSkpID" value="<?php echo $obj->ssSkpID; ?>">
                	<div class="pmd-card app-entry-card">
                        <div class="pmd-card-title">
                            <div class="media-left">
                                <?php $app->getIcon($this->icon); ?>
                            </div>
                            <div class="media-body media-middle">
                                <h2 class="pmd-card-title-text typo-fill-secondary">
                                    <a href="<?php echo $app->site; ?>/admin/<?php echo $app->act; ?>/<?php echo $obj->{$obj->primaryKey} > 0 ? 'edit/' . $obj->{$obj->primaryKey} : 'add'; ?>">
                                        <?php echo $obj->{$obj->primaryKey} > 0 ? 'Ubah' : 'Tambah'; ?> <?php echo $this->title; ?>
                                    </a>
                                </h2>
                            </div>
                        </div>
<?php
        $this->showMessage($actionResult->success, $actionResult->message, $actionResult->details);
?>
                        <div class="pmd-card-body">
                            <div class="group-fields clearfix row">
                                <div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="skpTgl" class="control-label">
                                            Tanggal SKPD
                                        </label>
                                        <p class="form-control-static"><?php echo $app->MySQLDateToIndonesia($objSKPD->skpTgl); ?></p>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="skpNo" class="control-label">
                                            Nomor SKPD
                                        </label>
                                        <p class="form-control-static"><?php echo $objSKPD->skpNoLengkap; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="wpNama" class="control-label">
                                            Nama
                                        </label>
                                        <p class="form-control-static"><?php echo $objSKPD->wpNama; ?></p>
                                     </div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="wpAlamat" class="control-label">
                                            Alamat
                                        </label>
                                    	<p class="form-control-static">
<?php 
        $alamat = array();
        if ($objSKPD->wpAlamat != '' && $objSKPD->wpAlamat != '-') {
            $alamat[] = $objSKPD->wpAlamat;
        }
        if ($objSKPD->wpKelID > 0) {
            $alamat[] = $objSKPD->kelTingkat.' '.$objSKPD->kelNama;
            $alamat[] = 'Kec. '.$objSKPD->kecNama;
        } else {
            if ($objSKPD->wpKelurahan != '') {
                $alamat[] = $objSKPD->wpKelurahan;
            }
            if ($objSKPD->wpKecamatan != '') {
                $alamat[] = 'Kec. '.$objSKPD->wpKecamatan;
            }
            if ($objSKPD->wpKabupaten != '') {
                $alamat[] = $objSKPD->wpKabupaten;
            }
            if ($objSKPD->wpProvinsi != '') {
                $alamat[] = $objSKPD->wpProvinsi;
            }
        }
        
        echo implode(", ", $alamat);
?>
                                    	</p>
                                    </div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                    <b>SSPD <?php echo $objSKPD->obyNama; ?></b>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-2 col-md-2 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssTgl" class="control-label">
                                            Tanggal
                                        </label>
									    <p class="form-control-static"><?php echo $app->MySQLDateToIndonesia($obj->ssTgl); ?></p>
                                        <input type="hidden" id="ssTgl" name="ssTgl" value="<?php echo $app->MySQLDateToNormal($obj->ssTgl); ?>">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssNo" class="control-label">
                                            No
                                        </label>
<?php
        if ($obj->ssID > 0) {
?>
                                        <p class="form-control-static"><?php echo $obj->ssNo; ?></p>
                                        <input type="hidden" id="ssNo" name="ssNo" value="<?php echo $obj->ssNo; ?>">
<?php
        } else {
?>
                                        <p class="form-control-static">Otomatis</p>
<?php
        }
?>
                                        <span class="pmd-textfield-focused"></span>
                                    </div>
                                </div>
                            </div>
<?php 
        if ($objSKPD->sptTahunPajakAwal == $objSKPD->sptTahunPajakAkhir) {
            if ($objSKPD->sptBulanPajakAwal == $objSKPD->sptBulanPajakAkhir) {
                $masa = $app->months[$objSKPD->sptBulanPajakAwal].' '.$objSKPD->sptTahunPajakAwal;
            } else {
                $masa = $app->months[$objSKPD->sptBulanPajakAwal].' s/d '.$app->months[$objSKPD->sptBulanPajakAkhir].' '.$objSKPD->sptTahunPajakAwal;
            }      
        } else {
            $masa = $app->months[$objSKPD->sptBulanPajakAwal].' '.$objSKPD->sptTahunPajakAwal.' s/d '.$app->months[$objSKPD->sptBulanPajakAkhir].' '.$objSKPD->sptTahunPajakAkhir;
        }
        
        $bulanPajak = $obj->ssID > 0 ? $obj->ssBulanPajak : $objSKPD->sptBulanPajakAwal;
        $tahunPajak = $obj->ssID > 0 ? $obj->ssTahunPajak : $objSKPD->sptTahunPajakAwal;
?>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="sptBulanPajakAwal" class="control-label">
                                            Masa Pajak
                                        </label>
                                        <p class="form-control-static"><?php echo $masa; ?></p>
                                		<input type="hidden" id="ssBulanPajak" name="ssBulanPajak" value="<?php echo $bulanPajak; ?>">
										<input type="hidden" id="ssTahunPajak" name="ssTahunPajak" value="<?php echo $tahunPajak; ?>">
									</div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row" style="margin-top:20px;" hidden>
                                <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssPembangkit" class="control-label">
                                            ID Pembangkit 
                                        </label>
                                        <input type="text" id="ssPembangkit" name="ssPembangkit" value="<?= $objSKPD->skpPembangkit;?>">
                                    </div>
                                </div>
                            </div>
<?php
        //TODO: Restoran
        if ($objSKPD->obyID == 2) {
?>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssRestoranNamaInstansi" class="control-label">
                                            Nama Instansi
                                        </label>
                                        <input class="form-control" id="ssRestoranNamaInstansi" name="ssRestoranNamaInstansi" maxlength="500" value="<?php echo $obj->ssRestoranNamaInstansi; ?>" autofocus>
                                        <span class="pmd-textfield-focused"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssRestoranNamaKegiatan" class="control-label">
                                            Nama Kegiatan
                                        </label>
                                        <input class="form-control" id="ssRestoranNamaKegiatan" name="ssRestoranNamaKegiatan" maxlength="500" value="<?php echo $obj->ssRestoranNamaKegiatan; ?>">
                                        <span class="pmd-textfield-focused"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssRestoranNoRekening" class="control-label">
                                            No. Rekening
                                        </label>
                                        <input class="form-control" id="ssRestoranNoRekening" name="ssRestoranNoRekening" maxlength="500" value="<?php echo $obj->ssRestoranNoRekening; ?>">
                                        <span class="pmd-textfield-focused"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssRestoranBelanja" class="control-label">
                                            Belanja
                                        </label>
                                        <input class="form-control" id="ssRestoranBelanja" name="ssRestoranBelanja" maxlength="500" value="<?php echo $obj->ssRestoranBelanja; ?>">
                                        <span class="pmd-textfield-focused"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssRestoranNoPenunjukan" class="control-label">
                                            No. Penunjukan
                                        </label>
                                        <input class="form-control" id="ssRestoranNoPenunjukan" name="ssRestoranNoPenunjukan" maxlength="500" value="<?php echo $obj->ssRestoranNoPenunjukan; ?>">
                                        <span class="pmd-textfield-focused"></span>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssRestoranTglPenunjukan" class="control-label">
                                            Tanggal Penunjukan
                                        </label>
                                        <input class="form-control datepicker" id="ssRestoranTglPenunjukan" name="ssRestoranTglPenunjukan" maxlength="10" value="<?php echo $app->MySQLDateToNormal($obj->ssRestoranTglPenunjukan); ?>">
                                        <span class="pmd-textfield-focused"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssRestoranDasarPenyetoranPersentase" class="control-label">
                                            Persentase Penyetoran
                                        </label>
                                        <input class="form-control" id="ssRestoranDasarPenyetoranPersentase" name="ssRestoranDasarPenyetoranPersentase" maxlength="500" value="<?php echo $obj->ssRestoranDasarPenyetoranPersentase; ?>">
                                        <span class="pmd-textfield-focused"></span>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssRestoranDasarPenyetoranNilai" class="control-label">
                                            Nilai Penyetoran
                                        </label>
                                        <input class="form-control" id="ssRestoranDasarPenyetoranNilai" name="ssRestoranDasarPenyetoranNilai" maxlength="500" value="<?php echo $obj->ssRestoranDasarPenyetoranNilai; ?>">
                                        <span class="pmd-textfield-focused"></span>
                                    </div>
                                </div>
                            </div>
<?php
        }
?>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssJumlahPokok" class="control-label">
                                            Jumlah Pokok
                                        </label>
                                        <p class="form-control-static"><?php echo $app->MySQLToMoney(($obj->ssID > 0) ? $obj->ssJumlahPokok : $objSKPD->skpJumlahPokok); ?></p>
                                        <input type="hidden" class="form-control" id="ssJumlahPokok" name="ssJumlahPokok" value="<?php echo $app->MySQLToMoney(($obj->ssID > 0) ? $obj->ssJumlahPokok : $objSKPD->skpJumlahPokok); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="group-fields clearfix row">
                                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                                    <div class="form-group form-group-sm">
                                        <label for="ssJumlahDenda" class="control-label">
                                            Jumlah Denda
                                        </label>
                                        <p class="form-control-static"><?php echo $app->MySQLToMoney(($obj->ssID > 0) ? $obj->ssJumlahDenda : $objSKPD->skpJumlahDenda); ?></p>
                                        <input type="hidden" class="form-control" id="ssJumlahDenda" name="ssJumlahDenda" maxlength="20" value="<?php echo $app->MySQLToMoney(($obj->ssID > 0) ? $obj->ssJumlahDenda : $objSKPD->skpJumlahDenda); ?>">
                                    </div>
                                </div>
                            </div>
							<!-- <div class="group-fields clearfix row">
								<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
									<div class="form-group form-group-sm">
										<label for="ssNamaSetor" class="control-label">
											Nama Penyetor
										</label>
										<input class="form-control" id="ssNamaSetor" name="ssNamaSetor" maxlength="50" value="<?php //echo $obj->ssNamaSetor; ?>" autofocus>
										<span class="pmd-textfield-focused"></span>
									</div>
								</div>
							</div> -->
                        </div>	
                        <div class="pmd-card-actions">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a class="btn btn-default" href="<?php echo $app->site; ?>/admin/PenerimaanSSPD/index">Batal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
    }
    
    public function previewPajakHotel($objSSPD) {
        global $app;
    
        $sql = "SELECT *
                FROM detailsptpdhotel
                WHERE dsptSptID='".$objSSPD->sptID."'";
        $arrDetailSPT = $app->queryArrayOfObjects($sql);
    
        $sql = "SELECT *, npaDasarHukum AS parentId, npaDasarHukum AS parentName, npaID AS id
                FROM nilaiperolehanair";
        $nilaiPerolehanAir = $app->queryArrayOfObjects($sql);
    
        //TODO;
        $kode = '4.1.1.01';
        $nama = 'Pajak Hotel';
    
        $bulanTahun = array();
    
        if ($objSSPD->sptBulanPajakAwal == $objSSPD->sptBulanPajakAkhir &&
            $objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
    
                $bulanTahun[] = $masaPajak.' '.$tahunPajak;
            } else if ($objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
    
                for ($i=$objSSPD->sptBulanPajakAwal; $i<=$objSSPD->sptBulanPajakAkhir; $i++) {
                    $bulanTahun[] = $app->months[$i].' '.$tahunPajak;
                }
            } else {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal." s.d. ".$objSSPD->sptTahunPajakAkhir;
    
                $month = strtotime($objSSPD->sptTahunPajakAwal.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAwal).'-01');
                $end = strtotime($objSSPD->sptTahunPajakAkhir.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAkhir).'-01');
                while($month <= $end) {
                    $bulanTahun[] = $app->months[date('n', $month)].' '.date('Y', $month);
                    $month = strtotime("+1 month", $month);
                }
            }
?>
    <table border="1" cellpadding="0" cellspacing="0"  style="width: 100%">
        <tr>
            <td style="text-align: center;padding: 10px;width: 50%">
                <table style="width: 100%">
                    <tr><td style="font-size: 12px"><b>PEMERINTAH KABUPATEN KAMPAR </b></td></tr>
                    <tr><td style="font-size: 15px"><b>BADAN PENDAPATAN DAERAH</b></td></tr>
                    <tr><td>Jalan Prof. M. Yamin, SH No. 83</td></tr>
                    <tr><td>BANGKINANG KOTA</td></tr>
                </table>    
            </td>
            <td style="text-align: center;padding: 12px;width: 50%">
                <table border="0" style="width: 100%">  
                    <tr style="text-align: center;">
                        <td colspan="3" style="font-size: 12px"><b>SURAT SETORAN</b></td>
                    </tr>
                    <tr>
                        <td style="margin: 0px;text-align: left">Nomor</td>
                        <td style="width: 10px">:</td>
                        <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssNo; ?></td>
                    </tr>
                    <tr>
                        <td style="margin: 0px;text-align: left">Bulan</td>
                        <td style="width: 10px">:</td>
                        <td style="width: available;text-align: left"><span> </span><?php echo $app->months[$objSSPD->ssBulanPajak]; ?></td>
                    </tr>
                    <tr>
                        <td style="margin: 0px;text-align: left">Tahun</td>
                        <td style="width: 10px">:</td>
                        <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssTahunPajak; ?></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <table border="0"style="width: 100%">
                    <tr><td colspan="3"></td></tr>
                    <tr>
                        <td style="width: 10%"><p style="text-align: left">  Nama</p></td>
                        <td style="width: 90%"><p style="text-align: left">  : <?php echo $objSSPD->wpNama; ?></p></td>
                    </tr>
                    <tr>
                        <td><p style="text-align: left">  Alamat</p></td>
                        <td><p style="text-align: left">  : <?php echo $objSSPD->wpAlamat; ?></p></td>
                    </tr>
                    <tr>
                        <td><p style="text-align: left">  NPWPD</p></td>
                        <td><p style="text-align: left">  : <?php echo $objSSPD->wpNPWPD; ?></p></td>
                    </tr>
                    <tr><td colspan="2"></td></tr>
                    <tr>
                        <td colspan="2"> Menyetor Berdasarkan Surat Ketetapan Setoran Bulanan No. <?php echo $objSSPD->skpNoLengkap; ?></td>
                    </tr>
                    <tr><td colspan="2"></td></tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2"><table border="1" cellpadding="2" cellspacing="0" style="border-collapse: collapse;width: 100%">
                    <tr style="text-align: center;border-bottom: solid 1px black" >
                        <td style="width: 20px">No.</td>
                        <td style="width: 80px">Ayat</td>
                        <td style="width: 330px">Rincian</td>
                        <td style="width: ">Jumlah</td>
                    </tr>
<?php 
        $jumlah = $objSSPD->skpJumlahPokok;
?>
                    <tr>
                        <td>1.</td>
                        <td><?php echo $kode; ?></td>
                        <td><?php echo $nama; ?></td>
                        <td align="right"><?php echo $app->MySQLToMoney($jumlah); ?></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2"> Dengan Huruf : <?php echo ucwords($app->terbilang($jumlah)); ?></td> 
        </tr>

    </table>
    
    <table border="1" width="100%">
    <tr>
        <td width="33%">
            <table style="text-align: center">
                <tr><td></td></tr>
                <tr><td>Ruang untuk Kas Register / Tanda Tangan Petugas Penerimaan</td></tr>
            </table>
        </td>
        <td width="34%">
            <table style="text-align: center">
                <tr><td></td></tr>
                <tr><td>Diterima Oleh :</td></tr>
                <tr><td>Bank Riau Kepri dan Bendahara Penerimaan</td></tr>
            </table>
        </td>
        <td width="33%">
            <table style="text-align: center">
                <tr><td></td></tr>
                <tr><td>Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->skpTgl); ?></td></tr>
                <tr><td>Penyetor</td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td>(<?php echo ($objSSPD->ssNamaSetor != '') ? $objSSPD->ssNamaSetor : '.............................................'; ?>)</td></tr>
                <tr><td></td></tr>
            </table>
        </td>
    </tr>
    </table>
<?php
    }

    public function previewPajakHiburan($objSSPD) {
        global $app;
        
        $sql = "SELECT *
                FROM detailsptpdhiburan
                    WHERE dsptSptID='".$objSSPD->sptID."'";
        $arrDetailSPT = $app->queryObject($sql);
        
        //TODO;
        $kode = '4.1.1.03';
        $nama = 'Pajak Hiburan';
        
        $bulanTahun = array();
        
        if ($objSSPD->sptBulanPajakAwal == $objSSPD->sptBulanPajakAkhir &&
            $objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                $bulanTahun[] = $masaPajak.' '.$tahunPajak;
            } else if ($objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                for ($i=$objSSPD->sptBulanPajakAwal; $i<=$objSSPD->sptBulanPajakAkhir; $i++) {
                    $bulanTahun[] = $app->months[$i].' '.$tahunPajak;
                }
            } else {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal." s.d. ".$objSSPD->sptTahunPajakAkhir;
        
                $month = strtotime($objSSPD->sptTahunPajakAwal.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAwal).'-01');
                $end = strtotime($objSSPD->sptTahunPajakAkhir.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAkhir).'-01');
                while($month <= $end) {
                    $bulanTahun[] = $app->months[date('n', $month)].' '.date('Y', $month);
                    $month = strtotime("+1 month", $month);
                }
            }
            ?>
            <table border="1" cellpadding="0" cellspacing="0"  style="width: 100%">
                <tr>
                    <td style="text-align: center;padding: 10px;width: 50%">
                        <table style="width: 100%">
                            <tr><td style="font-size: 12px"><b>PEMERINTAH KABUPATEN KAMPAR </b></td></tr>
                            <tr><td style="font-size: 15px"><b>BADAN PENDAPATAN DAERAH</b></td></tr>
                            <tr><td>Jalan Prof. M. Yamin, SH No. 83</td></tr>
                            <tr><td>BANGKINANG KOTA</td></tr>
                        </table>    
                    </td>
                    <td style="text-align: center;padding: 12px;width: 50%">
                        <table border="0" style="width: 100%">  
                            <tr style="text-align: center;">
                                <td colspan="3" style="font-size: 12px"><b>SURAT SETORAN</b></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Nomor</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssNo; ?></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Bulan</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $app->months[$objSSPD->ssBulanPajak]; ?></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Tahun</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssTahunPajak; ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <table border="0" width="100%">
                            <tr><td colspan="3"></td></tr>
                            <tr>
                                <td width="10%">Nama</td>
                                <td width="90%">: <?php echo $objSSPD->wpNama; ?></td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>: 
<?php 
    $alamat = array();
    if ($objSSPD->wpAlamat != '' && $objSSPD->wpAlamat != '-') {
        $alamat[] = $objSSPD->wpAlamat;
    }
    if ($objSSPD->wpKelID > 0) {
        $alamat[] = $objSSPD->kelTingkat.' '.$objSSPD->kelNama;
        $alamat[] = 'Kec. '.$objSSPD->kecNama;
    } else {
        if ($objSSPD->wpKelurahan != '') {
            $alamat[] = $objSSPD->wpKelurahan;
        }
        if ($objSSPD->wpKecamatan != '') {
            $alamat[] = 'Kec. '.$objSSPD->wpKecamatan;
        }
        if ($objSSPD->wpKabupaten != '') {
            $alamat[] = $objSSPD->wpKabupaten;
        }
        if ($objSSPD->wpProvinsi != '') {
            $alamat[] = $objSSPD->wpProvinsi;
        }
    }
    
    echo implode(", ", $alamat);
?>
                                </td>
                            </tr>
                            <tr>
                                <td>NPWPD</td>
                                <td>: <?php echo ($objSSPD->wpNPWPD != '') ? $objSSPD->wpNPWPD : '-'; ?></td>
                            </tr>
                            <tr><td colspan="2"></td></tr>
                            <tr>
                                <td colspan="2">Menyetor Berdasarkan Surat Ketetapan Setoran Bulanan No. <?php echo $objSSPD->skpNoLengkap; ?></td>
                            </tr>
                            <tr><td colspan="2"></td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><table border="1" cellpadding="2" cellspacing="0" style="border-collapse: collapse;width: 100%">
                            <tr style="text-align: center;border-bottom: solid 1px black" >
                                <td style="width: 20px">No.</td>
                                <td style="width: 80px">Ayat</td>
                                <td style="width: 330px">Rincian</td>
                                <td style="width: ">Jumlah</td>
                            </tr>
<?php 
        $jumlah = $objSSPD->skpJumlahPokok;
?>
                            <tr>
                                <td valign="top">1.</td>
                                <td valign="top"><?php echo $kode; ?></td>
                                <td valign="top"><?php echo $nama; ?></td>
                                <td valign="top" align="right"><?php echo $app->MySQLToMoney($jumlah); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"> Dengan Huruf : <?php echo ucwords($app->terbilang($jumlah)); ?></td> 
                </tr>
            </table>
            
            <table border="1" width="100%">
            <tr>
                <td width="33%">
                    <table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Ruang untuk Kas Register / Tanda Tangan Petugas Penerimaan</td></tr>
                    </table>
                </td>
                <td width="34%">
                    <table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Diterima Oleh :</td></tr>
                        <tr><td>Bank Riau Kepri dan Bendahara Penerimaan</td></tr>
                    </table>
                </td>
                <td width="33%">
                    <table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->skpTgl); ?></td></tr>
                        <tr><td>Penyetor</td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td>(<?php echo ($objSSPD->ssNamaSetor != '') ? $objSSPD->ssNamaSetor : '.............................................'; ?>)</td></tr>
                        <tr><td></td></tr>
                    </table>
                </td>
            </tr>
            </table>
        <?php
    }

    public function previewPajakParkir($objSSPD) {
        global $app;
        
        $sql = "SELECT *
                FROM detailsptpdparkir
                    WHERE dsptSptID='".$objSSPD->sptID."'";
        $arrDetailSPT = $app->queryObject($sql);
        
        //TODO;
        $kode = '4.1.1.00';
        $nama = 'Pajak Parkir';
        
        $bulanTahun = array();
        
        if ($objSSPD->sptBulanPajakAwal == $objSSPD->sptBulanPajakAkhir &&
            $objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                $bulanTahun[] = $masaPajak.' '.$tahunPajak;
            } else if ($objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                for ($i=$objSSPD->sptBulanPajakAwal; $i<=$objSSPD->sptBulanPajakAkhir; $i++) {
                    $bulanTahun[] = $app->months[$i].' '.$tahunPajak;
                }
            } else {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal." s.d. ".$objSSPD->sptTahunPajakAkhir;
        
                $month = strtotime($objSSPD->sptTahunPajakAwal.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAwal).'-01');
                $end = strtotime($objSSPD->sptTahunPajakAkhir.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAkhir).'-01');
                while($month <= $end) {
                    $bulanTahun[] = $app->months[date('n', $month)].' '.date('Y', $month);
                    $month = strtotime("+1 month", $month);
                }
            }
            ?>
            <table border="1" cellpadding="0" cellspacing="0"  style="width: 100%">
                <tr>
                    <td style="text-align: center;padding: 10px;width: 50%">
                        <table style="width: 100%">
                            <tr><td style="font-size: 12px"><b>PEMERINTAH KABUPATEN KAMPAR </b></td></tr>
                            <tr><td style="font-size: 15px"><b>BADAN PENDAPATAN DAERAH</b></td></tr>
                            <tr><td>Jalan Prof. M. Yamin, SH No. 83</td></tr>
                            <tr><td>BANGKINANG KOTA</td></tr>
                        </table>    
                    </td>
                    <td style="text-align: center;padding: 12px;width: 50%">
                        <table border="0" style="width: 100%">  
                            <tr style="text-align: center;">
                                <td colspan="3" style="font-size: 12px"><b>SURAT SETORAN</b></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Nomor</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssNo; ?></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Bulan</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $app->months[$objSSPD->ssBulanPajak]; ?></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Tahun</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssTahunPajak; ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <table border="0" width="100%">
                            <tr><td colspan="3"></td></tr>
                            <tr>
                                <td width="10%">Nama</td>
                                <td width="90%">: <?php echo $objSSPD->wpNama; ?></td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>: 
<?php 
    $alamat = array();
    if ($objSSPD->wpAlamat != '' && $objSSPD->wpAlamat != '-') {
        $alamat[] = $objSSPD->wpAlamat;
    }
    if ($objSSPD->wpKelID > 0) {
        $alamat[] = $objSSPD->kelTingkat.' '.$objSSPD->kelNama;
        $alamat[] = 'Kec. '.$objSSPD->kecNama;
    } else {
        if ($objSSPD->wpKelurahan != '') {
            $alamat[] = $objSSPD->wpKelurahan;
        }
        if ($objSSPD->wpKecamatan != '') {
            $alamat[] = 'Kec. '.$objSSPD->wpKecamatan;
        }
        if ($objSSPD->wpKabupaten != '') {
            $alamat[] = $objSSPD->wpKabupaten;
        }
        if ($objSSPD->wpProvinsi != '') {
            $alamat[] = $objSSPD->wpProvinsi;
        }
    }
    
    echo implode(", ", $alamat);
?>
                                </td>
                            </tr>
                            <tr>
                                <td>NPWPD</td>
                                <td>: <?php echo ($objSSPD->wpNPWPD != '') ? $objSSPD->wpNPWPD : '-'; ?></td>
                            </tr>
                            <tr><td colspan="2"></td></tr>
                            <tr>
                                <td colspan="2">Menyetor Berdasarkan Surat Ketetapan Setoran Bulanan No. <?php echo $objSSPD->skpNoLengkap; ?></td>
                            </tr>
                            <tr><td colspan="2"></td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><table border="1" cellpadding="2" cellspacing="0" style="border-collapse: collapse;width: 100%">
                            <tr style="text-align: center;border-bottom: solid 1px black" >
                                <td style="width: 20px">No.</td>
                                <td style="width: 80px">Ayat</td>
                                <td style="width: 330px">Rincian</td>
                                <td style="width: ">Jumlah</td>
                            </tr>
<?php 
        $jumlah = $objSSPD->skpJumlahPokok;
?>
                            <tr>
                                <td valign="top">1.</td>
                                <td valign="top"><?php echo $kode; ?></td>
                                <td valign="top"><?php echo $nama; ?></td>
                                <td valign="top" align="right"><?php echo $app->MySQLToMoney($jumlah); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"> Dengan Huruf : <?php echo ucwords($app->terbilang($jumlah)); ?></td> 
                </tr>
            </table>
            
            <table border="1" width="100%">
            <tr>
                <td width="33%">
                    <table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Ruang untuk Kas Register / Tanda Tangan Petugas Penerimaan</td></tr>
                    </table>
                </td>
                <td width="34%">
                    <table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Diterima Oleh :</td></tr>
                        <tr><td>Bank Riau Kepri dan Bendahara Penerimaan</td></tr>
                    </table>
                </td>
                <td width="33%">
                    <table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->skpTgl); ?></td></tr>
                        <tr><td>Penyetor</td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td>(<?php echo ($objSSPD->ssNamaSetor != '') ? $objSSPD->ssNamaSetor : '.............................................'; ?>)</td></tr>
                        <tr><td></td></tr>
                    </table>
                </td>
            </tr>
            </table>
        <?php
    }

    public function previewPajakMineralBukanLogamDanBatuan($objSSPD) {
        global $app;
        $sql = "SELECT *
                FROM detailsptpdmineral
                    WHERE dsptSptID='".$objSSPD->sptID."'";
        $arrDetailSPT = $app->queryObject($sql);
        
        //TODO;
        $kode = '4.1.1.11';
        $nama = 'Pajak Mineral Bukan Logam dan Batuan';
        
        $bulanTahun = array();
        
        if ($objSSPD->sptBulanPajakAwal == $objSSPD->sptBulanPajakAkhir &&
            $objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                $bulanTahun[] = $masaPajak.' '.$tahunPajak;
            } else if ($objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                for ($i=$objSSPD->sptBulanPajakAwal; $i<=$objSSPD->sptBulanPajakAkhir; $i++) {
                    $bulanTahun[] = $app->months[$i].' '.$tahunPajak;
                }
            } else {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal." s.d. ".$objSSPD->sptTahunPajakAkhir;
        
                $month = strtotime($objSSPD->sptTahunPajakAwal.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAwal).'-01');
                $end = strtotime($objSSPD->sptTahunPajakAkhir.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAkhir).'-01');
                while($month <= $end) {
                    $bulanTahun[] = $app->months[date('n', $month)].' '.date('Y', $month);
                    $month = strtotime("+1 month", $month);
                }
            }
            ?>
            <table border="1" cellpadding="0" cellspacing="0"  style="width: 100%">
                <tr>
                    <td style="text-align: center;padding: 10px;width: 50%">
                        <table style="width: 100%">
                            <tr><td style="font-size: 12px"><b>PEMERINTAH KABUPATEN KAMPAR </b></td></tr>
                            <tr><td style="font-size: 15px"><b>BADAN PENDAPATAN DAERAH</b></td></tr>
                            <tr><td>Jalan Prof. M. Yamin, SH No. 83</td></tr>
                            <tr><td>BANGKINANG KOTA</td></tr>
                        </table>    
                    </td>
                    <td style="text-align: center;padding: 12px;width: 50%">
                        <table border="0" style="width: 100%">  
                            <tr style="text-align: center;">
                                <td colspan="3" style="font-size: 12px"><b>SURAT SETORAN</b></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Nomor</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssNo; ?></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Bulan</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $app->months[$objSSPD->ssBulanPajak]; ?></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Tahun</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssTahunPajak; ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <table border="0" width="100%">
                            <tr><td colspan="3"></td></tr>
                            <tr>
                                <td width="10%">Nama</td>
                                <td width="90%">: <?php echo $objSSPD->wpNama; ?></td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>: 
<?php 
    $alamat = array();
    if ($objSSPD->wpAlamat != '' && $objSSPD->wpAlamat != '-') {
        $alamat[] = $objSSPD->wpAlamat;
    }
    if ($objSSPD->wpKelID > 0) {
        $alamat[] = $objSSPD->kelTingkat.' '.$objSSPD->kelNama;
        $alamat[] = 'Kec. '.$objSSPD->kecNama;
    } else {
        if ($objSSPD->wpKelurahan != '') {
            $alamat[] = $objSSPD->wpKelurahan;
        }
        if ($objSSPD->wpKecamatan != '') {
            $alamat[] = 'Kec. '.$objSSPD->wpKecamatan;
        }
        if ($objSSPD->wpKabupaten != '') {
            $alamat[] = $objSSPD->wpKabupaten;
        }
        if ($objSSPD->wpProvinsi != '') {
            $alamat[] = $objSSPD->wpProvinsi;
        }
    }
    
    echo implode(", ", $alamat);
?>
                                </td>
                            </tr>
                            <tr>
                                <td>NPWPD</td>
                                <td>: <?php echo ($objSSPD->wpNPWPD != '') ? $objSSPD->wpNPWPD : '-'; ?></td>
                            </tr>
                            <tr><td colspan="2"></td></tr>
                            <tr>
                                <td colspan="2">Menyetor Berdasarkan Surat Ketetapan Setoran Bulanan No. <?php echo $objSSPD->skpNoLengkap; ?></td>
                            </tr>
                            <tr><td colspan="2"></td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><table border="1" cellpadding="2" cellspacing="0" style="border-collapse: collapse;width: 100%">
                            <tr style="text-align: center;border-bottom: solid 1px black" >
                                <td style="width: 20px">No.</td>
                                <td style="width: 80px">Ayat</td>
                                <td style="width: 330px">Rincian</td>
                                <td style="width: ">Jumlah</td>
                            </tr>
<?php 
        $jumlah = $objSSPD->skpJumlahPokok;
?>
                            <tr>
                                <td valign="top">1.</td>
                                <td valign="top"><?php echo $kode; ?></td>
                                <td valign="top"><?php echo $nama."<br>Nama Kegiatan : ".$objSSPD->sptNama; ?>
<?php 

            echo '<br>'.$objNJOP->rekJenis;
?>
                                </td>
                                <td valign="top" align="right"><?php echo $app->MySQLToMoney($jumlah); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"> Dengan Huruf : <?php echo ucwords($app->terbilang($jumlah)); ?></td> 
                </tr>
            </table>
            
            <table border="1" width="100%">
            <tr>
                <td width="33%">
                    <table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Ruang untuk Kas Register / Tanda Tangan Petugas Penerimaan</td></tr>
                    </table>
                </td>
                <td width="34%">
                    <table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Diterima Oleh :</td></tr>
                        <tr><td>Bank Riau Kepri dan Bendahara Penerimaan</td></tr>
                    </table>
                </td>
                <td width="33%">
                    <table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->skpTgl); ?></td></tr>
                        <tr><td>Penyetor</td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td>(<?php echo ($objSSPD->ssNamaSetor != '') ? $objSSPD->ssNamaSetor : '.............................................'; ?>)</td></tr>
                        <tr><td></td></tr>
                    </table>
                </td>
            </tr>
            </table>
        <?php
    }
    
    public function previewPajakAirBawahTanah($objSSPD) {
        global $app;
        
        $sql = "SELECT *
                FROM detailsptpdairtanah
                WHERE dsptSptID='".$objSSPD->sptID."'
                ORDER BY dsptNo";
        $arrDetailSPT = $app->queryArrayOfObjects($sql);
        
        $sql = "SELECT *, npaDasarHukum AS parentId, npaDasarHukum AS parentName, npaID AS id
                FROM nilaiperolehanair";
        $nilaiPerolehanAir = $app->queryArrayOfObjects($sql);
        
        //TODO;
        $kode = '4.1.1.08';
        $nama = 'Pajak Air Bawah Tanah';
        
        $bulanTahun = array();
        
        if ($objSSPD->sptBulanPajakAwal == $objSSPD->sptBulanPajakAkhir &&
            $objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                $bulanTahun[] = $masaPajak.' '.$tahunPajak;
            } else if ($objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                for ($i=$objSSPD->sptBulanPajakAwal; $i<=$objSSPD->sptBulanPajakAkhir; $i++) {
                    $bulanTahun[] = $app->months[$i].' '.$tahunPajak;
                }
            } else {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal." s.d. ".$objSSPD->sptTahunPajakAkhir;
        
                $month = strtotime($objSSPD->sptTahunPajakAwal.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAwal).'-01');
                $end = strtotime($objSSPD->sptTahunPajakAkhir.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAkhir).'-01');
                while($month <= $end) {
                    $bulanTahun[] = $app->months[date('n', $month)].' '.date('Y', $month);
                    $month = strtotime("+1 month", $month);
                }
            }
?>
    <table border="1" cellpadding="0" cellspacing="0"  style="width: 100%">
        <tr>
            <td style="text-align: center;padding: 10px;width: 50%">
                <table style="width: 100%">
                    <tr><td style="font-size: 12px"><b>PEMERINTAH KABUPATEN KAMPAR </b></td></tr>
                    <tr><td style="font-size: 15px"><b>BADAN PENDAPATAN DAERAH</b></td></tr>
                    <tr><td>Jalan Prof. M. Yamin, SH No. 83</td></tr>
                    <tr><td>BANGKINANG KOTA</td></tr>
                </table>    
            </td>
            <td style="text-align: center;padding: 12px;width: 50%">
                <table border="0" style="width: 100%">  
                    <tr style="text-align: center;">
                        <td colspan="3" style="font-size: 12px"><b>SURAT SETORAN</b></td>
                    </tr>
                    <tr>
                        <td style="margin: 0px;text-align: left">Nomor</td>
                        <td style="width: 10px">:</td>
                        <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssNo; ?></td>
                    </tr>
                    <tr>
                        <td style="margin: 0px;text-align: left">Bulan</td>
                        <td style="width: 10px">:</td>
                        <td style="width: available;text-align: left"><span> </span><?php echo $app->months[$objSSPD->ssBulanPajak]; ?></td>
                    </tr>
                    <tr>
                        <td style="margin: 0px;text-align: left">Tahun</td>
                        <td style="width: 10px">:</td>
                        <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssTahunPajak; ?></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <table border="0"style="width: 100%">
                    <tr><td colspan="3"></td></tr>
                    <tr>
                        <td style="width: 10%"><p style="text-align: left">  Nama</p></td>
                        <td style="width: 90%"><p style="text-align: left">  : <?php echo $objSSPD->wpNama; ?></p></td>
                    </tr>
                    <tr>
                        <td><p style="text-align: left">  Alamat</p></td>
                        <td><p style="text-align: left">  : <?php echo $objSSPD->wpAlamat; ?></p></td>
                    </tr>
                    <tr>
                        <td><p style="text-align: left">  NPWPD</p></td>
                        <td><p style="text-align: left">  : <?php echo $objSSPD->wpNPWPD; ?></p></td>
                    </tr>
                    <tr><td colspan="2"></td></tr>
                    <tr>
                        <td colspan="2"> Menyetor Berdasarkan Surat Ketetapan Setoran Bulanan No. <?php echo $objSSPD->skpNoLengkap; ?></td>
                    </tr>
                    <tr><td colspan="2"></td></tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2"><table border="1" cellpadding="2" cellspacing="0" style="border-collapse: collapse;width: 100%">
                    <tr style="text-align: center;border-bottom: solid 1px black" >
                        <td style="width: 20px">No.</td>
                        <td style="width: 80px">Ayat</td>
                        <td style="width: 330px">Rincian</td>
                        <td style="width: ">Jumlah</td>
                    </tr>
<?php 
        $jumlah = 0;
        foreach ($arrDetailSPT as $k=>$v) {
            $parts = explode("___", $objSSPD->sptNilaiPerolehanAir);
            if (count($parts) == 2) {
                $npaDasarHukum = $parts[0];
                $npaKelompok = $parts[1];
            } else {
                $npaDasarHukum = "";
                $npaKelompok = "";
            }
            
            $sql = "SELECT *
                    FROM nilaiperolehanair
    		        WHERE npaDasarHukum='".$npaDasarHukum."' AND npaKelompok='".$npaKelompok."'
                    ORDER BY npaVolAirAwal";
            $arr2 = $app->queryArrayOfObjects($sql);
            
            $bayar = 0;
            
            foreach ($arr2 as $v2) {
                if ($v2->npaVolAirAwal <= $v->dsptProduksiBulanIni && $v->dsptProduksiBulanIni <= $v2->npaVolAirAkhir) {
                    $tarif20Persen = bc_mul(0.2, $v2->npaNilai, 1);
                    $bayar = bc_mul($tarif20Persen, $v->dsptProduksiBulanIni);
                }
            }

            $jumlah = bc_add($jumlah, $bayar);
        }
?>
                    <tr>
                        <td>1.</td>
                        <td><?php echo $kode; ?></td>
                        <td><?php echo $nama; ?></td>
                        <td align="right"><?php echo $app->MySQLToMoney($jumlah); ?></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2"> Dengan Huruf : <?php echo ucwords($app->terbilang($jumlah)); ?> Rupiah</td> 
        </tr>
        <!-- <tr>
            <td colspan="2">
                <table>
                    <tr>
                        <td></td>
                        <td>
                            <table style="text-align: center;">
                                <tr><td></td></tr>
                                <tr >
                                    <td style="font-size: 12px">Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->ssTgl); ?> </td>
                                </tr>
                                <tr>
                                    <td style="font-size: 12px;">Penyetor</td>
                                </tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr>
                                    <td style="font-size: 12px;"><b>( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</b></td>
                                </tr>
                                <tr><td></td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table style="text-align: center">
                    <tr><td></td></tr>
                    <tr>
                        <td style="font-size: 12px;">Mengetahui :</td>
                    </tr>
                    <tr>
                        <td style="font-size: 12px;"><b>KEPALA BADAN PENDAPATAN DAERAH</b></td>
                    </tr>
                    <tr>
                        <td style="font-size: 12px;"><b>KABUPATEN KAMPAR</b></td>
                    </tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>
            </td>
            <td>
                <table style="text-align: center">
                    <tr><td></td></tr>
                    <tr>
                        <td>Ruangan untuk Kas Register/Tanda Tangan/</td>
                    </tr>
                    <tr>
                        <td><b>BENDAHARAWAN PENERIMAAN BAPENDA</b></td>
                    </tr>
                    <tr>
                        <td><b>KABUPATEN KAMPAR</b></td>
                    </tr>
                </table>
            </td>
        </tr> -->
    </table>
    
    <table border="1" width="100%">
    <tr>
        <td width="33%">
        	<table style="text-align: center">
        		<tr><td></td></tr>
        		<tr><td>Ruang untuk Kas Register / Tanda Tangan Petugas Penerimaan</td></tr>
        	</table>
        </td>
        <td width="34%">
        	<table style="text-align: center">
        		<tr><td></td></tr>
        		<tr><td>Diterima Oleh :</td></tr>
        		<tr><td>Bank Riau Kepri dan Bendahara Penerimaan</td></tr>
        	</table>
        </td>
        <td width="33%">
        	<table style="text-align: center">
                <tr><td></td></tr>
                <tr><td>Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->skpTgl); ?></td></tr>
                <tr><td>Penyetor</td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td>(<?php echo ($objSSPD->ssNamaSetor != '') ? $objSSPD->ssNamaSetor : '.............................................'; ?>)</td></tr>
                <tr><td></td></tr>
            </table>
        </td>
    </tr>
	</table>
<?php
    }
    
    public function previewPajakPeneranganJalanUmumPLN($objSSPD) {
        global $app;
    
        $sql = "SELECT *
                FROM detailsptpdppjpln
                WHERE dsptSptID='".$objSSPD->sptID."'";

        $arrDetailSPT = $app->queryArrayOfObjects($sql);
        
        //TODO;

        $nama = 'Pajak Penerangan Jalan Tanpa Pembangkit';
    
        $bulanTahun = array();
    
        if ($objSSPD->sptBulanPajakAwal == $objSSPD->sptBulanPajakAkhir &&
            $objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
    
                $bulanTahun[] = $masaPajak.' '.$tahunPajak;
            } else if ($objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
    
                for ($i=$objSSPD->sptBulanPajakAwal; $i<=$objSSPD->sptBulanPajakAkhir; $i++) {
                    $bulanTahun[] = $app->months[$i].' '.$tahunPajak;
                }
            } else {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal." s.d. ".$objSSPD->sptTahunPajakAkhir;
    
                $month = strtotime($objSSPD->sptTahunPajakAwal.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAwal).'-01');
                $end = strtotime($objSSPD->sptTahunPajakAkhir.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAkhir).'-01');
                while($month <= $end) {
                    $bulanTahun[] = $app->months[date('n', $month)].' '.date('Y', $month);
                    $month = strtotime("+1 month", $month);
                }
            }
?>
    <table border="1">
        <tr>
            <td style="width: 45%">
                <table >
                    <tr>
                        <td style="width: 20%;"></td>
                        <td style="font-size: 8px; text-align: center; width: 80%; "> <b>PEMERINTAH KABUPATEN KAMPAR <br> BADAN PENDAPATAN DAERAH</b></td>
                    </tr>
                </table>
            </td>

            <td style="text-align: center; width: 35%;">
                SURAT SETORAN PAJAK DAERAH <br> <b>(SSPD)</b>
            </td>
            <td style="font-size: 6px; width: 18%;">
                -LEMBAR 1 : WP <br> -LEMBAR 2 : BAPENDA<br> - LEMBAR 3:BENDAHARA PENERIMAAN BAPENDA<br>-LEMBAR 4: BANK
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <table >
                    <tr>
                        <td></td>
                        <td></td>
                        <td> </td>
                    </tr>

                    <tr>
                        <td style="width: 20%;">NPWPD</td>
                        <td style="width: 2%;">:</td>
                        <td style="width: 50%;"> <?= $objSSPD->wpNPWPD; ?> </td>
                    </tr>
                    <tr>
                        <td>NAMA WP</td>
                        <td >:</td>
                        <td> <?= $objSSPD->wpNama ?></td>
                    </tr>
                    <tr>
                        <td>ALAMAT WP</td>
                        <td >:</td>
                        <td> <?= $objSSPD->wpAlamat ?></td>
                    </tr>
                    <tr>
                        <td>NAMA</td>
                        <td >:</td>
                        <td> PENGGUNAAN TENAGA LISTRIK PLN</td>
                    </tr>
                    <tr>
                        <td>JENIS PAJAK</td>
                        <td >:</td>
                        <td> PAJAK PENERANGAN JALAN</td>
                    </tr>
                    <tr>
                        <td>MASA PAJAK</td>
                        <td >:  </td>
                        <td> <?= $masaPajak.''.$tahunPajak; ?></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td ></td>
                        <td></td>
                    </tr>
                </table>
                <table border="1">
                    <tr>
                        <td style="width: 5%;"> No </td>
                        <td style="text-align: center; width: 50%;">URAIAN PEMBAYARAN</td>
                        <td style="text-align: center; width: 42%;">JUMLAH PEMBAYARAN (Rp.)</td>
                    </tr>
                    <tr >
                        <td  style="width: 5%; line-height: 20px;"> 1 </td>
                        <td style="font-size: 9px;"> PEMBAYARAN PPJ PLN MASA PAJAK  </td>
                        <td style="text-align: right;">  <?php echo $app->MySQLToMoney($objSSPD->ssJumlahPokok); ?> </td>
                    </tr>
                    <tr>
                        <td style="text-align: center;" colspan="2"> JUMLAH SETORAN PAJAK </td>
                        <td style="text-align: right;"> <?php echo $app->MySQLToMoney($objSSPD->ssJumlahPokok); ?>   </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="width: 25%;"> <b>TERBILANG</b></td>
            <td style="width: 73%;"colspan="2"> : <?php echo ucwords($app->terbilang($objSSPD->ssJumlahPokok)); ?></td>
        </tr>
        <tr>
            <td style="width: 50%;" colspan="2">
                <td>
                    <table>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"><b>Diterima Oleh Petugas Penerima</b></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td>Bangkinang,</td></tr>
                        <tr><td style="font-size: 7px; text-align: center;"><i>Cap dan tanda tangan</i></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"><b>TATA SOFIYAN</b></td></tr>
                        <tr><td style="text-align: center;">NIP. 19820918 200801 1 011</td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                    </table>
                </td>
            </td>
            <td style="width: 48%;">
                                    <table>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"><b>Diterima Oleh Petugas Penerima</b></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td>Bangkinang,</td></tr>
                        <tr><td style="font-size: 7px; text-align: center;"><i>Cap dan tanda tangan</i></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"><b>(WAJIB PAJAK)</b></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                        <tr><td style="text-align: center;"></td></tr>
                    </table>
            </td>
        </tr>
    </table>
<?php
    }

    public function previewPajakPeneranganJalanUmumNonPLN($objSSPD) {
        global $app;
    
        $sql = "SELECT *
                FROM detailsptpdairtanah
                WHERE dsptSptID='".$objSSPD->sptID."'
                ORDER BY dsptNo";
        $arrDetailSPT = $app->queryArrayOfObjects($sql);
    
        $sql = "SELECT *, npaDasarHukum AS parentId, npaDasarHukum AS parentName, npaID AS id
                FROM nilaiperolehanair";
        $nilaiPerolehanAir = $app->queryArrayOfObjects($sql);
    
        //TODO;
        $kode = '4.1.1.05.02';
        $nama = 'Pajak Penerangan Jalan Non PLN';
    
        $bulanTahun = array();
    
        if ($objSSPD->sptBulanPajakAwal == $objSSPD->sptBulanPajakAkhir &&
            $objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
    
                $bulanTahun[] = $masaPajak.' '.$tahunPajak;
            } else if ($objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
    
                for ($i=$objSSPD->sptBulanPajakAwal; $i<=$objSSPD->sptBulanPajakAkhir; $i++) {
                    $bulanTahun[] = $app->months[$i].' '.$tahunPajak;
                }
            } else {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal." s.d. ".$objSSPD->sptTahunPajakAkhir;
    
                $month = strtotime($objSSPD->sptTahunPajakAwal.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAwal).'-01');
                $end = strtotime($objSSPD->sptTahunPajakAkhir.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAkhir).'-01');
                while($month <= $end) {
                    $bulanTahun[] = $app->months[date('n', $month)].' '.date('Y', $month);
                    $month = strtotime("+1 month", $month);
                }
            }
?>
    <table border="1" cellpadding="0" cellspacing="0"  style="width: 100%">
        <tr>
            <td style="text-align: center;padding: 10px;width: 50%">
                <table style="width: 100%">
                    <tr><td style="font-size: 12px"><b>PEMERINTAH KABUPATEN KAMPAR </b></td></tr>
                    <tr><td style="font-size: 15px"><b>BADAN PENDAPATAN DAERAH</b></td></tr>
                    <tr><td>Jalan Prof. M. Yamin, SH No. 83</td></tr>
                    <tr><td>BANGKINANG KOTA</td></tr>
                </table>    
            </td>
            <td style="text-align: center;padding: 12px;width: 50%">
                <table border="0" style="width: 100%">  
                    <tr style="text-align: center;">
                        <td colspan="3" style="font-size: 12px"><b>SURAT SETORAN</b></td>
                    </tr>
                    <tr>
                        <td style="margin: 0px;text-align: left">Nomor</td>
                        <td style="width: 10px">:</td>
                        <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssNo; ?></td>
                    </tr>
                    <tr>
                        <td style="margin: 0px;text-align: left">Bulan</td>
                        <td style="width: 10px">:</td>
                        <td style="width: available;text-align: left"><span> </span><?php echo $app->months[$objSSPD->ssBulanPajak]; ?></td>
                    </tr>
                    <tr>
                        <td style="margin: 0px;text-align: left">Tahun</td>
                        <td style="width: 10px">:</td>
                        <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssTahunPajak; ?></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <table border="0"style="width: 100%">
                    <tr><td colspan="3"></td></tr>
                    <tr>
                        <td style="width: 10%"><p style="text-align: left">  Nama</p></td>
                        <td style="width: 90%"><p style="text-align: left">  : <?php echo $objSSPD->wpNama; ?></p></td>
                    </tr>
                    <tr>
                        <td><p style="text-align: left">  Alamat</p></td>
                        <td><p style="text-align: left">  : <?php echo $objSSPD->wpAlamat; ?></p></td>
                    </tr>
                    <tr>
                        <td><p style="text-align: left">  NPWPD</p></td>
                        <td><p style="text-align: left">  : <?php echo $objSSPD->wpNPWPD; ?></p></td>
                    </tr>
                    <tr><td colspan="2"></td></tr>
                    <tr>
                        <td colspan="2"> Menyetor Berdasarkan Surat Ketetapan Setoran Bulanan No. <?php echo $objSSPD->skpNoLengkap; ?></td>
                    </tr>
                    <tr><td colspan="2"></td></tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2"><table border="1" cellpadding="2" cellspacing="0" style="border-collapse: collapse;width: 100%">
                    <tr style="text-align: center;border-bottom: solid 1px black" >
                        <td style="width: 20px">No.</td>
                        <td style="width: 80px">Ayat</td>
                        <td style="width: 330px">Rincian</td>
                        <td style="width: ">Jumlah</td>
                    </tr>
<?php 
        $jumlah = $objSSPD->skpJumlahPokok;
?>
                    <tr>
                        <td>1.</td>
                        <td><?php echo $kode; ?></td>
                        <td><?php echo $nama; ?></td>
                        <td align="right"><?php echo $app->MySQLToMoney($jumlah); ?></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2"> Dengan Huruf : <?php echo ucwords($app->terbilang($jumlah)); ?></td> 
        </tr>
        <!-- <tr>
            <td colspan="2">
                <table>
                    <tr>
                        <td></td>
                        <td>
                            <table style="text-align: center;">
                                <tr><td></td></tr>
                                <tr >
                                    <td style="font-size: 12px">Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->ssTgl); ?> </td>
                                </tr>
                                <tr>
                                    <td style="font-size: 12px;">Penyetor</td>
                                </tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr><td></td></tr>
                                <tr>
                                    <td style="font-size: 12px;"><b>( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</b></td>
                                </tr>
                                <tr><td></td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table style="text-align: center">
                    <tr><td></td></tr>
                    <tr>
                        <td style="font-size: 12px;">Mengetahui :</td>
                    </tr>
                    <tr>
                        <td style="font-size: 12px;"><b>KEPALA BADAN PENDAPATAN DAERAH</b></td>
                    </tr>
                    <tr>
                        <td style="font-size: 12px;"><b>KABUPATEN KAMPAR</b></td>
                    </tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </table>
            </td>
            <td>
                <table style="text-align: center">
                    <tr><td></td></tr>
                    <tr>
                        <td>Ruangan untuk Kas Register/Tanda Tangan/</td>
                    </tr>
                    <tr>
                        <td><b>BENDAHARAWAN PENERIMAAN BAPENDA</b></td>
                    </tr>
                    <tr>
                        <td><b>KABUPATEN KAMPAR</b></td>
                    </tr>
                </table>
            </td>
        </tr> -->
    </table>
    
    <table border="1" width="100%">
    <tr>
        <td width="33%">
        	<table style="text-align: center">
        		<tr><td></td></tr>
        		<tr><td>Ruang untuk Kas Register / Tanda Tangan Petugas Penerimaan</td></tr>
        	</table>
        </td>
        <td width="34%">
        	<table style="text-align: center">
        		<tr><td></td></tr>
        		<tr><td>Diterima Oleh :</td></tr>
        		<tr><td>Bank Riau Kepri dan Bendahara Penerimaan</td></tr>
        	</table>
        </td>
        <td width="33%">
        	<table style="text-align: center">
                <tr><td></td></tr>
                <tr><td>Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->skpTgl); ?></td></tr>
                <tr><td>Penyetor</td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td></td></tr>
                <tr><td>(<?php echo ($objSSPD->ssNamaSetor != '') ? $objSSPD->ssNamaSetor : '.............................................'; ?>)</td></tr>
                <tr><td></td></tr>
            </table>
        </td>
    </tr>
	</table>
<?php
    }
    
    public function previewPajakRestoran($objSSPD) {
        global $app;
        
        $sql = "SELECT *
                FROM detailsptpdrestoran
                WHERE dsptSptID='".$objSSPD->sptID."'";
        $objDetailSPTPD = $app->queryObject($sql);
        
        $sql = "SELECT *
                FROM pengguna, pangkatgolonganruang
                WHERE pnID='".$objSSPD->skpDitandatanganiOleh."' AND pnPgrID=pgrID";
        $pengguna = $app->queryObject($sql);
        
        $sql = "SELECT robID AS id, CONCAT(angKode,'.',kelKode,'.',jenKode,'.',obyKode,'.',robKode) AS kode, robNama AS nama
                FROM anggaran, kelompok, jenis, obyek, rincianobyek
                WHERE angID=kelAngID AND kelID=jenKelID AND jenID=obyJenID AND obyID=robObyID";
        $rekening = $app->queryArrayOfObjects($sql);
        
        $bulanTahun = array();
        
        if ($objSSPD->sptBulanPajakAwal == $objSSPD->sptBulanPajakAkhir &&
            $objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
            $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal];
            $tahunPajak = $objSSPD->sptTahunPajakAwal;
    
            $bulanTahun[] = $masaPajak.' '.$tahunPajak;
        } else if ($objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
            $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
            $tahunPajak = $objSSPD->sptTahunPajakAwal;
    
            for ($i=$objSSPD->sptBulanPajakAwal; $i<=$objSSPD->sptBulanPajakAkhir; $i++) {
                $bulanTahun[] = $app->months[$i].' '.$tahunPajak;
            }
        } else {
            $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
            $tahunPajak = $objSSPD->sptTahunPajakAwal." s.d. ".$objSSPD->sptTahunPajakAkhir;
    
            $month = strtotime($objSSPD->sptTahunPajakAwal.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAwal).'-01');
            $end = strtotime($objSSPD->sptTahunPajakAkhir.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAkhir).'-01');
            while($month <= $end) {
                $bulanTahun[] = $app->months[date('n', $month)].' '.date('Y', $month);
                $month = strtotime("+1 month", $month);
            }
        }
?>
        <table border="1" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td width="40%" align="center">
                	<table>
                		<tr>
                			<td><b>PEMERINTAH KABUPATEN KAMPAR<br>BADAN PENDAPATAN DAERAH<br>Jl. Prof. M. Yamin, SH No. 83<br>B A N G K I N A N G</b></td>
                		</tr>
                	</table>
                </td>
                <td width="40%">
                	<table>
                		<tr>
                			<td colspan="2" align="center"><b>SURAT TANDA SETORAN<br>PAJAK RESTORAN</b></td>
                		</tr>
                    	<tr>
                    		<td width="20%">Masa</td>
                    		<td width="80%">: <?php echo strtoupper($masaPajak); ?></td>
                    	</tr>
                    	<tr>
                    		<td>Tahun</td>
                    		<td>: <?php echo $tahunPajak; ?></td>
                    	</tr>
                    </table>
                </td>
                <td width="20%">
                    <table>
                        <tr><td></td></tr>
                        <tr><td align="center"><b>NOMOR</b></td></tr>
                        <tr><td align="center"><?php echo $objSSPD->ssNo; ?></td></tr>
                        <tr><td></td></tr>
                    </table>
                </td>
            </tr>
            <tr>
            	<td colspan="3">
            		<table width="100%">
            			<tr>
            				<td colspan="2"></td>
            			</tr>
            			<tr>
            				<td width="25%">Nama</td>
            				<td width="75%">: <?php echo $objSSPD->wpNama; ?><?php echo ($objSSPD->ssRestoranNamaInstansi != '') ? ' / '.$objSSPD->ssRestoranNamaInstansi : ''; ?></td>
            			</tr>
<?php 
        $alamat = array();
        if ($objSSPD->wpAlamat != '' && $objSSPD->wpAlamat != '-') {
            $alamat[] = $objSSPD->wpAlamat;
        }
        $alamat[] = $objSSPD->kelTingkat.' '.$objSSPD->kelNama;
        $alamat[] = 'Kec. '.$objSSPD->kecNama;
?>
            			<tr>
            				<td>Alamat</td>
            				<td>: <?php echo strtoupper(implode(', ', $alamat)); ?></td>
            			</tr>
                        <tr>
                        	<td>NPWPD</td>
                        	<td>: -</td>
                        </tr>
                        <tr>
            				<td colspan="2"></td>
            			</tr>
            			<tr>
            				<td>Nama Kegiatan</td>
            				<td>: <?php echo ($objSSPD->ssRestoranNamaKegiatan != '') ? $objSSPD->ssRestoranNamaKegiatan : '-'; ?></td>
            			</tr>
                        <tr>
            				<td colspan="2"></td>
            			</tr>
            			<tr>
            				<td>No. Rekening</td>
            				<td>: <?php echo ($objSSPD->ssRestoranNoRekening != '') ? $objSSPD->ssRestoranNoRekening : '-'; ?></td>
            			</tr>
            			<tr>
            				<td>Belanja</td>
            				<td>: <?php echo ($objSSPD->ssRestoranBelanja != '') ? $objSSPD->ssRestoranBelanja : '-'; ?></td>
            			</tr>
            			<tr>
            				<td>Surat Penunjukan</td>
            				<td>: No. <?php echo ($objSSPD->ssRestoranNoPenunjukan != '') ? $objSSPD->ssRestoranNoPenunjukan : '-'; ?>, Tgl. <?php echo ($objSSPD->ssRestoranTglPenunjukan != '0000-00-00') ? $app->MySQLDateToIndonesia($objSSPD->ssRestoranTglPenunjukan) : '-'; ?></td>
            			</tr>
                        <tr>
            				<td colspan="2"></td>
            			</tr>
            		</table>
                </td>
            </tr>
            <tr>
            	<td colspan="3">
            		<table width="100%">
            			<tr>
            				<td colspan="2"></td>
            			</tr>
            			<tr>
            				<td colspan="2">I. DASAR PENYETORAN</td>
            			</tr>
            			<tr>
            				<td width="2%"></td>
            				<td width="98%">10% x Rp. <?php echo $app->MySQLToMoney($objDetailSPTPD->dsptDasarPengenaanPajak); ?> = Rp. <?php echo $app->MySQLToMoney($objDetailSPTPD->dsptPajakTerutang); ?></td>
            			</tr>
            		</table>
            	</td>
            </tr>
            <tr>
            	<td colspan="3">
            		<table width="100%">
            			<tr>
            				<td colspan="3"></td>
            			</tr>
            			<tr>
            				<td colspan="3">II. JUMLAH SETORAN</td>
            			</tr>
            			<tr>
            				<td width="2%"></td>
            				<td colspan="2" width="98%">Untuk tanggal 7 - 14 - 21 - 28*)</td>
            			</tr>
            			<tr>
            				<td></td>
            				<td colspan="2">Jumlah Pajak yang harus disetor :</td>
            			</tr>
            			<tr>
            				<td></td>
            				<td width="18%">Adalah</td>
            				<td width="80%">: <?php echo $app->MySQLToMoney(bc_add($objDetailSPTPD->dsptPajakTerutang, $objDetailSPTPD->dsptSanksiAdministrasi)); ?></td>
            			</tr>
            			<tr>
            				<td></td>
            				<td>Dengan huruf</td>
            				<td>: <?php echo ucwords($app->terbilang(bc_add($objDetailSPTPD->dsptPajakTerutang, $objDetailSPTPD->dsptSanksiAdministrasi))); ?> Rupiah</td>
            			</tr>
            			<tr>
            				<td colspan="3"></td>
            			</tr>
            		</table>
            	</td>
            </tr>
        </table>
		
		<table border="1" width="100%">
        <tr>
            <td width="33%">
            	<table style="text-align: center">
            		<tr><td></td></tr>
            		<tr><td>Ruang untuk Kas Register / Tanda Tangan Petugas Penerimaan</td></tr>
            	</table>
            </td>
            <td width="34%">
            	<table style="text-align: center">
            		<tr><td></td></tr>
            		<tr><td>Diterima Oleh :</td></tr>
            		<tr><td>Bank Riau Kepri dan Bendahara Penerimaan</td></tr>
            	</table>
            </td>
            <td width="33%">
            	<table style="text-align: center">
                    <tr><td></td></tr>
                    <tr><td>Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->skpTgl); ?></td></tr>
                    <tr><td>Penyetor</td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td>(<?php echo ($objSSPD->ssNamaSetor != '') ? $objSSPD->ssNamaSetor : '.............................................'; ?>)</td></tr>
                    <tr><td></td></tr>
                </table>
            </td>
        </tr>
    	</table>
<?php
    }
    
    public function previewPajakReklame($objSSPD) {
        global $app;
        
        $sql = "SELECT *
                FROM detailsptpdreklame
                WHERE dsptSptID='".$objSSPD->sptID."'";
        $arrDetailSPT = $app->queryObject($sql);
        
        //TODO;
        $kode = '4.1.1.04';
        $nama = 'Pajak Reklame';
        
        $bulanTahun = array();
        
        if ($objSSPD->sptBulanPajakAwal == $objSSPD->sptBulanPajakAkhir &&
            $objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                $bulanTahun[] = $masaPajak.' '.$tahunPajak;
            } else if ($objSSPD->sptTahunPajakAwal == $objSSPD->sptTahunPajakAkhir) {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal;
        
                for ($i=$objSSPD->sptBulanPajakAwal; $i<=$objSSPD->sptBulanPajakAkhir; $i++) {
                    $bulanTahun[] = $app->months[$i].' '.$tahunPajak;
                }
            } else {
                $masaPajak = $app->months[$objSSPD->sptBulanPajakAwal]." s.d. ".$app->months[$objSSPD->sptBulanPajakAkhir];
                $tahunPajak = $objSSPD->sptTahunPajakAwal." s.d. ".$objSSPD->sptTahunPajakAkhir;
        
                $month = strtotime($objSSPD->sptTahunPajakAwal.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAwal).'-01');
                $end = strtotime($objSSPD->sptTahunPajakAkhir.'-'.sprintf('%02d', $objSSPD->sptBulanPajakAkhir).'-01');
                while($month <= $end) {
                    $bulanTahun[] = $app->months[date('n', $month)].' '.date('Y', $month);
                    $month = strtotime("+1 month", $month);
                }
            }
            ?>
            <table border="1" cellpadding="0" cellspacing="0"  style="width: 100%">
                <tr>
                    <td style="text-align: center;padding: 10px;width: 50%">
                        <table style="width: 100%">
                            <tr><td style="font-size: 12px"><b>PEMERINTAH KABUPATEN KAMPAR </b></td></tr>
                            <tr><td style="font-size: 15px"><b>BADAN PENDAPATAN DAERAH</b></td></tr>
                            <tr><td>Jalan Prof. M. Yamin, SH No. 83</td></tr>
                            <tr><td>BANGKINANG KOTA</td></tr>
                        </table>    
                    </td>
                    <td style="text-align: center;padding: 12px;width: 50%">
                        <table border="0" style="width: 100%">  
                            <tr style="text-align: center;">
                                <td colspan="3" style="font-size: 12px"><b>SURAT SETORAN</b></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Nomor</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssNo; ?></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Bulan</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $app->months[$objSSPD->ssBulanPajak]; ?></td>
                            </tr>
                            <tr>
                                <td style="margin: 0px;text-align: left">Tahun</td>
                                <td style="width: 10px">:</td>
                                <td style="width: available;text-align: left"><span> </span><?php echo $objSSPD->ssTahunPajak; ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <table border="0" width="100%">
                            <tr><td colspan="3"></td></tr>
                            <tr>
                                <td width="10%">Nama</td>
                                <td width="90%">: <?php echo $objSSPD->wpNama; ?></td>
                            </tr>
                            <tr>
                                <td>Alamat</td>
                                <td>: 
<?php 
    $alamat = array();
    if ($objSSPD->wpAlamat != '' && $objSSPD->wpAlamat != '-') {
        $alamat[] = $objSSPD->wpAlamat;
    }
    if ($objSSPD->wpKelID > 0) {
        $alamat[] = $objSSPD->kelTingkat.' '.$objSSPD->kelNama;
        $alamat[] = 'Kec. '.$objSSPD->kecNama;
    } else {
        if ($objSSPD->wpKelurahan != '') {
            $alamat[] = $objSSPD->wpKelurahan;
        }
        if ($objSSPD->wpKecamatan != '') {
            $alamat[] = 'Kec. '.$objSSPD->wpKecamatan;
        }
        if ($objSSPD->wpKabupaten != '') {
            $alamat[] = $objSSPD->wpKabupaten;
        }
        if ($objSSPD->wpProvinsi != '') {
            $alamat[] = $objSSPD->wpProvinsi;
        }
    }
    
    echo implode(", ", $alamat);
?>
                                </td>
                            </tr>
                            <tr>
                                <td>NPWPD</td>
                                <td>: <?php echo ($objSSPD->wpNPWPD != '') ? $objSSPD->wpNPWPD : '-'; ?></td>
                            </tr>
                            <tr><td colspan="2"></td></tr>
                            <tr>
                                <td colspan="2">Menyetor Berdasarkan Surat Ketetapan Setoran Bulanan No. <?php echo $objSSPD->skpNoLengkap; ?></td>
                            </tr>
                            <tr><td colspan="2"></td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><table border="1" cellpadding="2" cellspacing="0" style="border-collapse: collapse;width: 100%">
                            <tr style="text-align: center;border-bottom: solid 1px black" >
                                <td style="width: 20px">No.</td>
                                <td style="width: 80px">Ayat</td>
                                <td style="width: 330px">Rincian</td>
                                <td style="width: ">Jumlah</td>
                            </tr>
<?php 
        $jumlah = $objSSPD->skpJumlahPokok;
?>
                            <tr>
                                <td valign="top">1.</td>
                                <td valign="top"><?php echo $kode; ?></td>
                                <td valign="top"><?php echo $nama; ?>
<?php 
        if ($arrDetailSPT->dsptKelompok == 'Permanen') {
            $sql = "SELECT *
                    FROM nilaijualreklame
                    WHERE rekID='".$arrDetailSPT->dsptRekID."'";
            $objNJOP = $app->queryObject($sql);
            
            echo '<br>'.$objNJOP->rekJenis;
            
            $sql = "SELECT *
                    FROM lokasireklame
                    WHERE lokID='".$arrDetailSPT->dsptLokID."'";
            $objLokasi = $app->queryObject($sql);
            
            echo '<br>Lokasi: '.$objLokasi->lokJalan;
            
            echo '<br>Panjang: '.$arrDetailSPT->dsptPanjang.' M, Lebar: '.$arrDetailSPT->dsptLebar.' M, Tinggi: '.$arrDetailSPT->dsptTinggi.' M';
            echo '<br>Sudut Pandang: '.$arrDetailSPT->dsptArah;
        } else {
            //Insidentil
            echo '<br>Panjang: '.$arrDetailSPT->dsptPanjang.' M, Lebar: '.$arrDetailSPT->dsptLebar.' M';
        }
?>
                                </td>
                                <td valign="top" style="text-align:right;"><?php echo $app->MySQLToMoney($jumlah); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"> Dengan Huruf : <?php echo ucwords($app->terbilang($jumlah)); ?></td> 
                </tr>
                <!-- <tr>
                    <td colspan="2">
                        <table>
                            <tr>
                                <td></td>
                                <td>
                                    <table style="text-align: center;">
                                        <tr><td></td></tr>
                                        <tr >
                                            <td style="font-size: 12px">Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->ssTgl); ?> </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 12px;">Penyetor</td>
                                        </tr>
                                        <tr><td></td></tr>
                                        <tr><td></td></tr>
                                        <tr><td></td></tr>
                                        <tr><td></td></tr>
                                        <tr><td></td></tr>
                                        <tr><td></td></tr>
                                        <tr>
                                            <td style="font-size: 12px;"><b>( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</b></td>
                                        </tr>
                                        <tr><td></td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <table style="text-align: center">
                            <tr><td></td></tr>
                            <tr>
                                <td style="font-size: 12px;">Mengetahui :</td>
                            </tr>
                            <tr>
                                <td style="font-size: 12px;"><b>KEPALA BADAN PENDAPATAN DAERAH</b></td>
                            </tr>
                            <tr>
                                <td style="font-size: 12px;"><b>KABUPATEN KAMPAR</b></td>
                            </tr>
                            <tr><td></td></tr>
                            <tr><td></td></tr>
                            <tr><td></td></tr>
                            <tr><td></td></tr>
                            <tr><td></td></tr>
                            <tr><td></td></tr>
                            <tr><td></td></tr>
                            <tr><td></td></tr>
                            <tr><td></td></tr>
                        </table>
                    </td>
                    <td>
                        <table style="text-align: center">
                            <tr><td></td></tr>
                            <tr>
                                <td>Ruangan untuk Kas Register/Tanda Tangan/</td>
                            </tr>
                            <tr>
                                <td><b>BENDAHARAWAN PENERIMAAN BAPENDA</b></td>
                            </tr>
                            <tr>
                                <td><b>KABUPATEN KAMPAR</b></td>
                            </tr>
                        </table>
                    </td>
                </tr> -->
            </table>
            
            <table border="1" width="100%">
            <tr>
                <td width="33%">
                	<table style="text-align: center">
                		<tr><td></td></tr>
                		<tr><td>Ruang untuk Kas Register / Tanda Tangan Petugas Penerimaan</td></tr>
                	</table>
                </td>
                <td width="34%">
                	<table style="text-align: center">
                		<tr><td></td></tr>
                		<tr><td>Diterima Oleh :</td></tr>
                		<tr><td>Bank Riau Kepri dan Bendahara Penerimaan</td></tr>
                	</table>
                </td>
                <td width="33%">
                	<table style="text-align: center">
                        <tr><td></td></tr>
                        <tr><td>Bangkinang, <?php echo $app->MySQLDateToIndonesia($objSSPD->skpTgl); ?></td></tr>
                        <tr><td>Penyetor</td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td></td></tr>
                        <tr><td>(<?php echo ($objSSPD->ssNamaSetor != '') ? $objSSPD->ssNamaSetor : '.............................................'; ?>)</td></tr>
                        <tr><td></td></tr>
                    </table>
                </td>
            </tr>
        	</table>
        <?php
    }

    public function preview($id) {
        global $app;
        
        $sql = "SELECT *
                FROM suratsetoran
                LEFT JOIN skpd ON ssSkpID=skpID
                LEFT JOIN sptpd ON skpSptID=sptID
                LEFT JOIN pelayanan ON sptLyID=lyID
                LEFT JOIN jenispelayanan ON lyJlyID=jlyID
                LEFT JOIN obyek ON lyObyID=obyID
                LEFT JOIN wajibpajak ON lyWpID=wpID
                LEFT JOIN jeniswajibpajak ON wpJwpID=jwpID
                LEFT JOIN kelurahan ON wpKelID=kelID
                LEFT JOIN kecamatan ON kelKecID=kecID
                WHERE ssID='".$id."'";
        $objSSPD = $app->queryObject($sql);
        if (!$objSSPD) {
        	echo "SSPD dengan id ".$id." tidak ditemukan";
        	exit();
        }
        
        //Persiapkan PDF-nya
        $pdf = new Report('SSPD No '.$objSSPD->ssNo, 'SKPD No '.$objSSPD->ssNo.'.pdf', $app->getUser()->name, $app->name);
         
        $pdf->create();
        $pdf->addPage();

        ob_start();
        
        switch ($objSSPD->obyID) {
            case 1:
                $this->previewPajakHotel($objSSPD);
                break;
            case 2:
                $this->previewPajakRestoran($objSSPD);
                break;
            case 3:
                $this->previewPajakHiburan($objSSPD);
                break;
            case 4:
                $this->previewPajakReklame($objSSPD);
                break;
            case 5:
                if ($objSSPD->skpPembangkit == 1) {
                    $this->previewPajakPeneranganJalanUmumPLN($objSSPD);
                } else {
                    $this->previewPajakPeneranganJalanUmumNonPLN($objSSPD);
                }
                break;
            case 6:
                $this->previewPajakParkir($objSSPD);
                break;
            case 7:
                $this->previewPajakAirBawahTanah($objSSPD);
                break;
            case 9:
                $this->previewPajakMineralBukanLogamDanBatuan($objSSPD);
                break;
        }
        
        $content = ob_get_contents();
        ob_clean();
        
        $pdf->writeHTML($content);
        
        $pdf->output();
    }
}
?>