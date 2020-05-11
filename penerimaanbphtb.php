<?php
global $app;

if (!$app) {
	exit();
}

class PenerimaanBPHTBController extends Controller {
	/**
     * @var PenerimaanBPHTBModel
     */
	public $model;
	
	/**
     * @var PenerimaanBPHTBView
     */
	public $view;
	
	public function __construct() {
		$this->setType(self::TYPE_SINGLE);
		$this->setTitle('Setoran BPHTB');
		$this->setIcon('local_atm');

        $this->allowAccess('Administrator');
        $this->allowAccess('Penerimaan');
        $this->allowAccess('Pejabat');
		
		//Create model and view
		$this->prepare();
	}
}

class PenerimaanBPHTBModel extends Model {
    public $table = 'bphtb';
    public $primaryKey = 'bphID';
    
    public $bphID = 0;
	public $bphTgl = '0000-00-00';
	public $bphNo = 0;
    public $bphNoLengkap = '';
    public $bphNoLengkapPelayanan = '';
	public $bphNama = '';
	public $bphNIK = '';
	public $bphNPWP = '';
	public $bphAlamat = '';
	public $bphBlokKavNo = '';
	public $bphRT = '';
	public $bphRW = '';
	public $bphKelID = 0;
	public $bphKelurahan = '';
	public $bphKecamatan = '';
	public $bphKabupaten = '';
	public $bphProvinsi = '';
	public $bphKodePos = '';
	public $bphNoTelp = '';
	public $bphPenjualNama = '';
	public $bphPenjualNIK = '';
	public $bphObyekNomor = '';
	public $bphObyekLetak = '';
	public $bphObyekBlokKavNo = '';
	public $bphObyekRT = '';
	public $bphObyekRW = '';
	public $bhpObyekKelID = 0;
	public $bphPerolehanJenis = '';
	public $bphTransaksiJenis = '';
	public $bphPerolehanTahun = 0;
	public $bphNomorSertifikat = '';
	public $bphBumiLuas = 0;
	public $bphBumiNJOP = 0;
	public $bphBumiBersamaLuas = 0;
	public $bphBumiBersamaNJOP = 0;
	public $bphBangunanLuas = 0;
	public $bphBangunanNJOP = 0;
	public $bphBangunanBersamaLuas = 0;
	public $bphBangunanBersamaNJOP = 0;
	public $bphNilaiPasar = 0;
	public $bphNPOP = 0;
	public $bphNPOPTKP = 0;
	public $bphNPOPBPHTBDibayar = 0;
	public $bphSetoranBerdasarkan = '';
	public $bphDitandatanganiOleh = 0;
	
	public $bphVerifikasi = '';
	public $bphVerifikasiOleh = 0;
	public $bphVerifikasiPada = '0000-00-00 00:00:00';
	
	/**
	 * Menghapus sebuah record
	 *
	 * @param int $id
	 *
	 * @return ActionResult $actionResult Hasil aksi
	 */
	public function delete($id) {
		global $app;

		//Get object
		try {
			$sql = "SELECT *
					FROM suratsetoran
					WHERE ssID='".$id."'";
			$objSSPD = $app->queryObject($sql);
			if (!$objSSPD) {
				throw new ActionException("SSPD dengan ID <b>{$id}</b> tidak ditemukan");
			} else {
				//jika sudah setor, maka tak boleh hapus.
				if ($objSSPD->ssStatusSetor == 'Sudah Setor') {
					throw new ActionException("SSPD dengan ID <b>{$id}</b> telah disetor");
				}
			}
		} catch (ActionException $ex) {
			return $ex->getActionResult();
		}
		
		//--Check dependencies
		$actionResult = new ActionResult();
		
		if (!$actionResult->hasErrorMessages()) {
			$sql = "DELETE FROM suratsetoran
					WHERE ssID='".$id."'";
			$app->query($sql);
			
			$actionResult->setMessage(ActionResult::DELETE_SUCCESSFUL, "Data \"{$objSSPD->ssNo}\"");
			
			$app->log("Menghapus data SSPD dengan id ".$objSSPD->ssID.", nomor ".$objSSPD->ssNo);
		} else {
			$actionResult->setMessage(ActionResult::DELETE_FAILED, "Data \"{$objSSPD->ssNo}\"");
		}

		return $actionResult;
	}

	/**
	 * Mendapatkan semua record
	 *
	 * @return PageResult $pageResult Hasil aksi
	 */
	public function findAll() {
		global $app;

		$pageResult = new PageResult($app->act, $app->connection);

		//--Set criteria
		$pageResult->addCriteria("ssNo LIKE :no", "no%");
		$pageResult->addCriteria("bphNoLengkap LIKE :nobphtb", "nobphtb%");
		$pageResult->addCriteria("lyNamaNotaris LIKE :namanotaris", "%namanotaris%");
		$pageResult->addCriteria("bphNama LIKE :nama", "%nama%");
		$pageResult->addCriteria("bphAlamat LIKE :alamat", "%alamat%");
		$pageResult->addCriteria("bphVerifikasi=:verifikasi", "verifikasi", "--Verifikasi--");
		
		$name = 'status';
		$defaultValue = '--Status--';
		$validator = '';
		$value = $pageResult->getPageVar($app->act, 'filter'.$name, $defaultValue, $validator);
		if ($value != $defaultValue) {
			//$pageResult->params[':'.$name] = $value;
			switch($value) {
				case 'Belum Dibuat':
					$pageResult->criteria[] = "ssbph.ssBphID IS NULL AND ssbpk.ssBpkID IS NULL";
					break;
				case 'Sudah Dibuat':
					$pageResult->criteria[] = "(ssbph.ssBphID > 0 OR ssbpk.ssBpkID > 0) AND bphTgl<>'0000-00-00' AND bphNo>0";
					break;
			}
		}
		$pageResult->filters[$name] = $value;
		$pageResult->defaults[$name] = $defaultValue;
		
		//--Set sort order
		$pageResult->setSortBy("bphNoLengkap", "DESC");
		
		//Query
		$sql = "SELECT %s
				FROM {$this->table}
				LEFT JOIN pelayanan ON lyNoLengkap=bphNoLengkapPelayanan
				LEFT JOIN kelurahan ON bphKelID=kelID
				LEFT JOIN kecamatan ON kelKecID=kecID
				LEFT JOIN suratsetoran AS ssbph ON ssbph.ssBphID=bphID
				LEFT JOIN suratsetoran AS ssbpk ON ssbpk.ssBpkID=bphBpkID AND bphBpkID>0
				%s %s %s";

		$pageResult->setPageVars($sql);
		
		$fields = "*, IF(ssbph.ssBphID > 0 OR ssbpk.ssBpkID > 0, 'Sudah Dibuat', 'Belum Dibuat') AS status";
		$pageResult->data = $app->queryArrayOfObjects($pageResult->getSQL($fields), $pageResult->params);
	    
		return $pageResult;
	}
	
	public function toggleColumns() {
		global $app;
		
		$disabledCheckboxes = array();
		foreach ($disabledCheckboxes as $name) {
			$_SESSION[$app->act.'-column-'.$name] = VIEW::COLUMN_SHOW;
		}
		
		$checkboxes = array(
			'ssID',
			'ssTgl',
			'ssNo',
			'bphTgl',
			'bphNoLengkap',
			'lyTgl',
			'lyNoLengkap',
			'lyNamaNotaris',
			'bphNama',
			'bphAlamat',
			'bphDibuatOleh',
			'bphDiubahOleh',
			'bphVerifikasi',
		    'status',
		    'ssNamaSetor',
		    'ssWaktuSetor',
		    'ssJumlahSetor',
		    'ssManualSetor',
// Start ondri TTE
		    'tte'
// End ondri TTE
		);
		foreach ($checkboxes as $name) {
			$_SESSION[$app->act.'-column-'.$name] = (isset($_REQUEST[$name])) ? VIEW::COLUMN_SHOW : VIEW::COLUMN_HIDE;
		}
		
		header("Location:".$app->site."/admin/".$app->act."/index");
	}
}

class PenerimaanBPHTBView extends View {
    /**
	 * Menampilkan halaman pengelolaan data
	 *
	 * @param PageResult $pageResult Hasil aksi
	 */
	public function index($pageResult) {
		global $app;
		
		$sql = "SELECT pnID AS id, pnNama AS name
                FROM pengguna";
		$pengguna = $app->queryArrayOfValues($sql);
?>
	<div class="container-fluid app-container">
		<div class="row">
			<div class="col-lg-12 col-md-12">
				<div class="pmd-card app-index-card">
					<div class="pmd-card-title">
						<div class="media-left">
							<?php $app->getIcon($this->icon); ?>
						</div>
						<div class="media-body media-middle">
							<h2 class="pmd-card-title-text typo-fill-secondary">
								<a href="<?php echo $app->site; ?>/admin/<?php echo $app->act; ?>">
									<?php echo $this->title; ?>
								</a>
							</h2>
						</div>
					</div>
<?php
		$this->showMessage($pageResult->success, $pageResult->message);
//Start Ondri TTE

		if (isset($_SESSION['classAlert'])) {
?>
	<div role="alert" class="<?php echo $_SESSION['classAlert']; ?>" style="border-radius:0px; margin-bottom:0px;">
		<button aria-label="Close" data-dismiss="alert" class="close" type="button"><span aria-hidden="true">&times;</span></button>
		<i class="material-icons md-dark pmd-sm icon-primary" style="float:left; margin-right:10px; vertical-align:middle;">info_outline</i>
		<small>
			<?php  echo $_SESSION['messageAlert']; ?> 
		</small>
	</div>
<?php			
		unset($_SESSION['classAlert']);
		unset($_SESSION['messageAlert']);
		}
//End Ondri TTE
?>
					<div class="pmd-toolbar">
						<div class="pmd-toolbar-entry pull-left">
<?php
		$checkboxes = array(
			array('ssID', 'ID', VIEW::COLUMN_HIDE),
			array('ssTgl', 'Tanggal SSPD'),
			array('ssNo', 'Nomor SSPD'),
			array('bphTgl', 'Tanggal'),
			array('bphNoLengkap', 'Nomor Billing'),
			array('lyTgl', 'Tanggal Pelayanan', VIEW::COLUMN_HIDE),
			array('lyNoLengkap', 'Nomor Pelayanan', VIEW::COLUMN_HIDE),
			array('lyNamaNotaris', 'Nama Notaris'),
			array('bphNama', 'Nama'),
			array('bphAlamat', 'Alamat'),
			array('bphDibuatOleh', 'Dibuat Oleh', VIEW::COLUMN_HIDE),
			array('bphDiubahOleh', 'Diubah Oleh', VIEW::COLUMN_HIDE),
			array('bphVerifikasi', 'Verifikasi'),
			array('status', 'Status'),
			array('ssNamaSetor', 'Penyetor', VIEW::COLUMN_HIDE),
			array('ssWaktuSetor', 'Waktu', VIEW::COLUMN_HIDE),
			array('ssJumlahSetor', 'Jumlah', VIEW::COLUMN_HIDE),
			array('ssManualSetor', 'Manual', VIEW::COLUMN_HIDE),
//Start ondri TTE
			array('tte', 'TTE')

//End ondri TTE
		);
		$this->showColumnSelectorDialog($pageResult, $checkboxes);
?>
<!-- //Start Ondri TTE -->
			<!-- Tombol Ajukan TTE -->
			<button type="button" class="btn btn-sm pmd-ripple-effect btn-default" id="btnAjukanTTE" data-target="#formAjukanTTE" data-toggle="modal">Ajukan TTE</button>
<!--//End Ondri TTE  -->
						</div>
						<div class="pmd-toolbar-filter pull-right">
							<div class="form-group form-group-sm pull-left" style="margin:0px;">
								<input type="search" class="form-control input-filter" id="filterno" name="filterno" value="<?php echo $pageResult->filters['no']; ?>" placeholder="Nomor SSPD"><span class="pmd-textfield-focused"></span>
							</div>
							<div class="form-group form-group-sm pull-left" style="margin:0px;">
								<input type="search" class="form-control input-filter" id="filternobphtb" name="filternobphtb" value="<?php echo $pageResult->filters['nobphtb']; ?>" placeholder="Nomor Billing"><span class="pmd-textfield-focused"></span>
							</div>
							<div class="form-group form-group-sm pull-left" style="margin:0px;">
								<input type="search" class="form-control input-filter" id="filternamanotaris" name="filternamanotaris" value="<?php echo $pageResult->filters['namanotaris']; ?>" placeholder="Nama Notaris"><span class="pmd-textfield-focused"></span>
							</div>
							<div class="form-group form-group-sm pull-left" style="margin:0px;">
								<input type="search" class="form-control input-filter" id="filternama" name="filternama" value="<?php echo $pageResult->filters['nama']; ?>" placeholder="Nama"><span class="pmd-textfield-focused"></span>
							</div>
							<div class="form-group form-group-sm pull-left" style="margin:0px;">
								<input type="search" class="form-control input-filter" id="filteralamat" name="filteralamat" value="<?php echo $pageResult->filters['alamat']; ?>" placeholder="Alamat"><span class="pmd-textfield-focused"></span>
							</div>
							<div class="form-group form-group-sm pull-left" style="margin:0px;">
<?php
		$verifikasi = array('Belum Verifikasi'=>'Belum Verifikasi', 'Diterima'=>'Diterima', 'Ditolak'=>'Ditolak');
		$this->createSelect('filterverifikasi', $verifikasi, $pageResult->filters['verifikasi'], array($pageResult->defaults['verifikasi']=>$pageResult->defaults['verifikasi']), 'select-simple form-control pmd-select2');
?>
							</div>
							<div class="form-group form-group-sm pull-left" style="margin:0px;">
<?php
		$status = array('Belum Dibuat' => 'Belum Dibuat', 'Sudah Dibuat' => 'Sudah Dibuat');
		$this->createSelect('filterstatus', $status, $pageResult->filters['status'], array($pageResult->defaults['status']=>$pageResult->defaults['status']), 'select-with-search form-control pmd-select2');
?>
							</div>
<?php	if (!$pageResult->isFiltered()) { ?>
							<button type="button" class="btn btn-sm pmd-ripple-effect btn-default" id="btnFilterFind" name="btnFilterFind" title="Terapkan Filter"><i class="material-icons pmd-sm">search</i></button>
<?php	} else { ?>
							<button type="button" class="btn btn-sm pmd-ripple-effect btn-default" id="btnFilterFind" name="btnFilterFind" title="Terapkan Filter"><i class="material-icons pmd-sm">search</i></button><button type="button" class="btn btn-sm pmd-ripple-effect btn-danger" id="btnFilterCancel" name="btnFilterCancel" value="Hapus Filter"><i class="material-icons pmd-sm">close</i></button>
<?php	} ?>
						</div>
						<div style="clear:both;"></div>
					</div>
					<div class="pmd-card-body">
						<div class="pmd-table-card">
							<!-- Action AJukan TTE -->
							<form class="form-horizontal" action="<?php echo $app->site; ?>/admin/BPHTB/tte" method="post">
							<table class="table table-sm pmd-table table-striped table-hover table-bordered table-selected table-header-sticked" id="table-propeller">
<?php 
		$this->addTableHeader('no', '#', false, 28, 'right');
		$this->addTableHeader('aksi', 'Aksi', false, $this->countVisibleColumnSelector() > 0 ? 70 : 0);
		if ($_SESSION[$app->act.'-column-ssID']) {
			$this->addTableHeader('ssID', 'ID');
		}
	    if ($_SESSION[$app->act.'-column-ssTgl']) {
			$this->addTableHeader('ssTgl', 'Tanggal SSPD', true, 120);
		}
		if ($_SESSION[$app->act.'-column-ssNo']) {
			$this->addTableHeader('ssNo', 'Nomor SSPD', true, 100);
		}
	    if ($_SESSION[$app->act.'-column-bphTgl']) {
			$this->addTableHeader('bphTgl', 'Tanggal', true, 80);
		}
		if ($_SESSION[$app->act.'-column-bphNoLengkap']) {
			$this->addTableHeader('bphNoLengkap', 'Nomor Billing', true, 120);
		}
	    if ($_SESSION[$app->act.'-column-lyTgl']) {
			$this->addTableHeader('lyTgl', 'Tanggal Pelayanan', true, 100);
		}
		if ($_SESSION[$app->act.'-column-lyNoLengkap']) {
			$this->addTableHeader('lyNoLengkap', 'Nomor Pelayanan', true, 100);
		}
		if ($_SESSION[$app->act.'-column-lyNamaNotaris']) {
			$this->addTableHeader('lyNamaNotaris', 'Nama Notaris', true, 100);
		}
		if ($_SESSION[$app->act.'-column-bphNama']) {
			$this->addTableHeader('bphNama', 'Nama');
		}
		if ($_SESSION[$app->act.'-column-bphAlamat']) {
			$this->addTableHeader('bphAlamat', 'Alamat');
		}
		if ($_SESSION[$app->act.'-column-bphDibuatOleh']) {
			$this->addTableHeader('bphDibuatOleh', 'Dibuat Oleh');
		}
		if ($_SESSION[$app->act.'-column-bphDiubahOleh']) {
			$this->addTableHeader('bphDiubahOleh', 'Diubah Oleh');
		}
		if ($_SESSION[$app->act.'-column-bphVerifikasi']) {
			$this->addTableHeader('bphVerifikasi', 'Verifikasi', true, 100);
		}
		if ($_SESSION[$app->act.'-column-status']) {
			$this->addTableHeader('status', 'Status', true, 120);
		}
		if ($_SESSION[$app->act.'-column-ssNamaSetor']) {
			$this->addTableHeader('ssNamaSetor', 'Penyetor', true, 120);
		}
		if ($_SESSION[$app->act.'-column-ssWaktuSetor']) {
			$this->addTableHeader('ssWaktuSetor', 'Waktu', true, 120);
		}	
		if ($_SESSION[$app->act.'-column-ssJumlahSetor']) {
			$this->addTableHeader('ssJumlahSetor', 'Jumlah', true, 120);
		}
		if ($_SESSION[$app->act.'-column-ssManualSetor']) {
			$this->addTableHeader('ssManualSetor', 'Manual', true, 120);
		}
	//Start ondri TTE
		if ($_SESSION[$app->act.'-column-tte']) {
			$this->addTableHeader('tte', 'TTE', true, 120);
		}
	//End ondri TTE
		echo $this->showTableHeaders($pageResult->sort, $pageResult->dir);
?>
								<tbody>
<?php
		if (count($pageResult->data) > 0) {
			$no = $pageResult->start + 1;
			$noPertama = $no;
			foreach ($pageResult->data as $v) {
?>
									<tr>
										<td data-title="No" style="text-align:right;">
											<?php echo $no; ?>
										</td>
										<td data-title="Aksi">
<?php 
                if (is_null($v->ssID)) {
?>
											<a <?php echo ($noPertama == $no) ? 'id="btnEdit"' : ''; ?> href="<?php echo $app->site; ?>/admin/SSPDBPHTB/add/<?php echo $v->bphID; ?>"><?php $app->getIcon('edit'); ?></a>
<?php 
                } else {
?>
											<a <?php echo ($noPertama == $no) ? 'id="btnEdit"' : ''; ?> href="<?php echo $app->site; ?>/admin/SSPDBPHTB/edit/<?php echo $v->ssID; ?>"><?php $app->getIcon('edit'); ?></a>

											<a <?php echo ($noPertama == $no) ? 'id="btnDelete"' : ''; ?> href="javascript:deleteRecord('<?php echo addslashes($v->ssNo); ?>', <?php echo $v->ssID; ?>)"><?php $app->getIcon('delete_forever'); ?></a>

<!-- 	//Start ondri TTE -->
<?php
					if ($v->bphTTE!="Selesai" && $v->bphTTE!="Sedang Diajukan") {
?>

											<input title="Ceklis Untuk Diajukan" type="checkbox" class="pmd-checkbox-ripple-effect " name="bphTTEID[<?=$v->bphID?>]" ><span class="pmd-checkbox-label">&nbsp;</span>
<?php
					}if ($v->bphTTE=="Selesai") {
?>
									<a <?php echo ($noPertama == $no) ? 'id="btnPreview"' : ''; ?> title="Unduh Dokumen TTE" href="<?php echo $app->site; ?>/admin/TTEBPHTB/preview?idDok=<?php echo hash("sha1", $v->bphID); ?>" <?php $app->getIcon('verified_user'); ?></a>
<?php 
					}
// End ondri TTE
                    if ($v->bphTgl != '0000-00-00' && $v->bphNo > 0) {

?>
										<a <?php echo ($noPertama == $no) ? 'id="btnPreview"' : ''; ?> href="<?php echo $app->site; ?>/admin/BPHTB/preview/<?php echo $v->bphID; ?>?html=0" target="_blank"><?php $app->getIcon('print'); ?></a>
<?php
												
                    }                    
                }
?>
										</td>
<?php 
				if ($_SESSION[$app->act.'-column-ssID']) {
?>
										<td data-title="ID"><?php echo (!is_null($v->ssID)) ? $v->ssID : 0; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-ssTgl']) {
?>
										<td data-title="Tanggal SSPD"><?php echo (!is_null($v->ssTgl) && $v->ssTgl != '0000-00-00') ? $app->MySQLDateToNormal($v->ssTgl) : '-'; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-ssNo']) {
?>
										<td data-title="Nomor SSPD"><?php echo !empty($v->ssNo) ? $v->ssNo : '-'; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-bphTgl']) {
?>
										<td data-title="Tanggal"><?php echo (!is_null($v->bphTgl) && $v->bphTgl != '0000-00-00') ? $app->MySQLDateToNormal($v->bphTgl) : '-'; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-bphNoLengkap']) {
?>
										<td data-title="Nomor Billing"><?php echo !empty($v->bphNoLengkap) ? $v->bphNoLengkap : '-'; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-lyTgl']) {
?>
										<td data-title="Tanggal Pelayanan"><?php echo $app->MySQLDateToNormal($v->lyTgl); ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-lyNoLengkap']) {
?>
										<td data-title="Nomor Pelayanan"><?php echo $v->lyNoLengkap; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-lyNamaNotaris']) {
?>
										<td data-title="Nama Notaris"><?php echo $v->lyNamaNotaris; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-bphNama']) {
?>
										<td data-title="Nama"><?php echo $v->bphNama; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-bphAlamat']) {
?>
										<td data-title="Alamat"><?php echo $v->bphAlamat; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-bphDibuatOleh']) {
?>
										<td data-title="Dibuat Oleh"><?php echo isset($pengguna[$v->bphDibuatOleh]) ? $pengguna[$v->bphDibuatOleh] : ''; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-bphDiubahOleh']) {
?>
										<td data-title="Diubah Oleh"><?php echo isset($pengguna[$v->bphDiubahOleh]) ? $pengguna[$v->bphDiubahOleh] : ''; ?></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-bphVerifikasi']) {
				    switch ($v->bphVerifikasi) {
				        case 'Diterima':
				            $badge = 'badge badge-success';
				            break;
				        case 'Ditolak':
				            $badge = 'badge badge-error';
				            break;
				        case 'Belum Verifikasi':
				            $badge = 'badge badge-warning';
				            break;
				    }
	
?>
										<td data-title="Verifikasi"><span class="<?php echo $badge; ?>"><?php echo $v->bphVerifikasi; ?></span></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-status']) {
				    if ($v->status == 'Sudah Dibuat') {
				        $badge = 'badge badge-success';
    				    $text = 'Sudah Dibuat';
				    } else {
				        $badge = 'badge badge-error';
    				    $text = 'Belum Dibuat';
				    }
?>
										<td data-title="Status"><span class="<?php echo $badge; ?>"><?php echo $text; ?></span></td>
<?php 
				}
				if ($_SESSION[$app->act.'-column-ssNamaSetor']) {
?>
										<td data-title="Penyetor"><?php echo $v->ssNamaSetor; ?></td>
<?php
				}
				if ($_SESSION[$app->act.'-column-ssWaktuSetor']) {
?>
										<td data-title="Penyetor"><?php echo $v->ssWaktuSetor; ?></td>
<?php
				}
				if ($_SESSION[$app->act.'-column-ssJumlahSetor']) {
?>
										<td data-title="Penyetor"><?php echo $v->ssJumlahSetor; ?></td>
<?php
				}
				if ($_SESSION[$app->act.'-column-ssManualSetor']) {
?>
										<td data-title="Penyetor"><?php echo $v->ssManualSetor; ?></td>
<?php
				}

// Start ondri TTE

				if ($_SESSION[$app->act.'-column-tte']) {

			        if ($v->bphTTE == 'Selesai') {
				        $badgeTTE = 'badge badge-success';
				        $textTTE = 'Selesai';
			        }elseif ($v->bphTTE=='Sedang Diajukan') {
			        	$badgeTTE = 'badge badge-warning';
				        $textTTE = 'Sedang Diajukan';
			        } else {
			            $badgeTTE = 'badge badge-error';
				        $textTTE = 'Belum Ada';
			        }
?>
										<td data-title="TTE"><span class="<?php echo $badgeTTE; ?>"><?php echo $textTTE; ?></span></td>
<?php 
				}
// End ondri TTE
?>
									</tr>
<?php
                $no++;
			}
		} else {
?>
									<tr><td colspan="<?php echo $this->countTableHeaders(); ?>">Tidak ada data</td></tr>
<?php
		}
?>
								</tbody>
							</table>
<!-- //Start Ondri TTE -->
<!-- Form Ajukan TTE -->
				<div tabindex="-1" class="modal fade" id="formAjukanTTE" style="display: none;" aria-hidden="true">
					<div class="modal-dialog">
						<div class="modal-content">
							<div class="modal-header pmd-modal-bordered">
								<button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
								<h2 class="pmd-card-title-text">Pilih Pejabat</h2>
							</div>
							<div class="modal-body">
								<div class="form-group pmd-textfield pmd-textfield-floating-label">
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
									<div class="form-group form-group-sm">
<?php 
					$sql = "SELECT pnID AS id, pnNama AS name
				FROM pengguna
                WHERE pnLevelAkses='Pimpinan' OR pnLevelAkses='Pejabat'
				ORDER BY pnNama";
		$pengguna = $app->queryArrayOfObjects($sql);
		$this->createSelect('bphTTEOleh', $pengguna, '', array(), 'form-control pmd-select2');
?>
									<!-- <input type="text" class="mat-input form-control" id="name" value=""> -->
								</div>
							</div>
						</div>
							</div>
							<div class="pmd-modal-action">
                            <button type="submit" class="btn btn-primary">Ajukan</button>
							</div>									
								</form>
							</div>

						</div>
					</div>
				</div>
			<!-- End Form Ajukan TTE  -->
<!-- //End  Ondri TTE -->
							<?php echo $this->displayNavigation($pageResult); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
		$this->writePageResultScript($pageResult);
	}
}
?>