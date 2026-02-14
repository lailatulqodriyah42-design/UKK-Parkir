<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

$conn = mysqli_connect("localhost", "root", "", "parkir");
if (!$conn) { die("Koneksi gagal: " . mysqli_connect_error()); }

if (isset($_POST['login'])) {
    $u = mysqli_real_escape_string($conn, $_POST['username']); 
    $p = mysqli_real_escape_string($conn, $_POST['password']);
    $q = mysqli_query($conn, "SELECT * FROM tb_user WHERE username='$u' AND password='$p' AND status_aktif=1");
    if (mysqli_num_rows($q) > 0) {
        $d = mysqli_fetch_assoc($q);
        $_SESSION['role'] = $d['role'];
        $_SESSION['nama'] = $d['nama_lengkap'];
        $_SESSION['id_user'] = $d['id_user'];
        header("Location: index.php"); exit();
    } else { $error = "Username atau Password salah!"; }
}
if (isset($_GET['logout'])) { 
session_destroy(); header("Location: index.php"); exit(); }

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$uid  = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 0;

if (isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];

    if ($aksi == 'tambah_user') {
        mysqli_query($conn, "INSERT INTO tb_user (nama_lengkap, username, password, role, status_aktif) VALUES (
		'$_POST[nama]', '$_POST[user]', '$_POST[pass]', '$_POST[role_u]', 1)");
    } elseif ($aksi == 'edit_user') {
        mysqli_query($conn, "UPDATE tb_user SET 
		nama_lengkap='$_POST[nama]', 
		username='$_POST[user]', 
		password='$_POST[pass]', role='$_POST[role_u]' WHERE id_user='$_POST[id]'");
    } elseif ($aksi == 'hapus_user') {
        mysqli_query($conn, "DELETE FROM tb_user WHERE id_user='$_POST[id]'");
    }
    elseif ($aksi == 'tambah_tarif') {
        mysqli_query($conn, "INSERT INTO tb_tarif (jenis_kendaraan, tarif_per_jam) VALUES (
		'$_POST[jenis]', '$_POST[tarif]')");
    } elseif ($aksi == 'edit_tarif') {
        mysqli_query($conn, "UPDATE tb_tarif SET 
		jenis_kendaraan='$_POST[jenis]', 
		tarif_per_jam='$_POST[tarif]' WHERE id_tarif='$_POST[id]'");
    } elseif ($aksi == 'hapus_tarif') {
        mysqli_query($conn, "DELETE FROM tb_tarif WHERE id_tarif='$_POST[id]'");
    }
    elseif ($aksi == 'tambah_area') {
        mysqli_query($conn, "INSERT INTO tb_area_parkir (nama_area, kapasitas, terisi) VALUES (
		'$_POST[nama_a]', '$_POST[kapasitas]', 0)");
    } elseif ($aksi == 'edit_area') {
        mysqli_query($conn, "UPDATE tb_area_parkir SET 
		nama_area='$_POST[nama_a]', kapasitas='$_POST[kapasitas]' WHERE id_area='$_POST[id]'");
    } elseif ($aksi == 'hapus_area') {
        mysqli_query($conn, "DELETE FROM tb_area_parkir WHERE id_area='$_POST[id]'");
    }

    elseif ($aksi == 'masuk_parkir') {
        $plat = mysqli_real_escape_string($conn, $_POST['plat']);
        $warna = mysqli_real_escape_string($conn, $_POST['warna']);
        $pemilik = mysqli_real_escape_string($conn, $_POST['pemilik']);
        mysqli_query($conn, "INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES (
		'$plat', '$_POST[jenis_k]', '$warna', '$pemilik', '$uid')");
        $id_k = mysqli_insert_id($conn);
        mysqli_query($conn, "INSERT INTO tb_transaksi (id_kendaraan, waktu_masuk, id_tarif, status, id_user, id_area, biaya_total, durasi_jam) VALUES ('$id_k', NOW(), '$_POST[id_t]', 'masuk', '$uid', '$_POST[id_a]', 0, 0)");
        mysqli_query($conn, "UPDATE tb_area_parkir SET terisi = terisi + 1 WHERE id_area = '$_POST[id_a]'");
        mysqli_query($conn, "INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES (
		'$uid', 'Masuk: $plat', NOW())");
    }

    elseif ($aksi == 'keluar_parkir') {
        $id_p = $_POST['id_p'];
    
        $qt = mysqli_query($conn, "SELECT t.*, tr.tarif_per_jam FROM tb_transaksi t JOIN tb_tarif tr ON t.id_tarif = tr.id_tarif WHERE t.id_parkir = '$id_p'");
        $dt = mysqli_fetch_assoc($qt);
        
        $masuk = new DateTime($dt['waktu_masuk']);
        $keluar = new DateTime();
        $diff = $masuk->diff($keluar);
        $durasi = $diff->h + ($diff->days * 24);
        if($diff->i > 0) $durasi++; 
        if($durasi == 0) $durasi = 1;

        $total = $durasi * $dt['tarif_per_jam'];

        mysqli_query($conn, "UPDATE tb_transaksi SET waktu_keluar=NOW(), 
		durasi_jam='$durasi', 
		biaya_total='$total', 
		status='keluar' WHERE id_parkir='$id_p'");
        mysqli_query($conn, "UPDATE tb_area_parkir SET terisi = terisi - 1 WHERE id_area = '$dt[id_area]'");
        mysqli_query($conn, "INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES (
		'$uid', 'Keluar ID: $id_p', NOW())");
    }
	elseif ($aksi == 'hapus_parkir') {
        $res = mysqli_query($conn, "SELECT id_area, status FROM tb_transaksi WHERE id_parkir='$_POST[id_p]'");
        $data = mysqli_fetch_assoc($res);
        
        if($data['status'] == 'masuk') {
            mysqli_query($conn, "UPDATE tb_area_parkir SET terisi = terisi - 1 WHERE id_area = '$data[id_area]'");
        }
        
        mysqli_query($conn, "DELETE FROM tb_transaksi WHERE id_parkir='$_POST[id_p]'");
        mysqli_query($conn, "DELETE FROM tb_kendaraan WHERE id_kendaraan='$_POST[id_k]'");
    }
    header("Location: index.php?page=$page"); exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Aplikasi Parkir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; }
        .top-menu-card { background: white; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
		padding: 10px; margin-bottom: 30px; }
        .nav-pills .nav-link { color: #555; font-weight: 500; border-radius: 10px; margin: 0 5px; }
        .nav-pills .nav-link.active { background-color: #0d6efd !important; color: white; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>

<?php if (!$role): ?>
  <div class="container d-flex justify-content-center align-items-center" style="height: 100vh;">
        <div class="card p-4 shadow-lg" style="width: 400px; border-top: 6px solid #0d6efd;">
            <div class="text-center mb-4"><h3 class="fw-bold text-primary">LOGIN</h3></div>
            <form method="POST">
                <div class="mb-3"><label class="small fw-bold">Username</label>
                <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-4"><label class="small fw-bold">Password</label>
                <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100 py-2">MASUK</button>
            </form>
        </div>
    </div>
<?php else: ?>

<div class="container py-4">
    <div class="top-menu-card no-print text-center">
        <div class="d-flex justify-content-between align-items-center px-3 mb-2 border-bottom pb-2">
            <h5 class="m-0 fw-bold text-primary">UKK PARKIR</h5>
            <div class="small">User: <strong><?= $_SESSION['nama'] ?></strong>
            </div>
        </div>
        <ul class="nav nav-pills justify-content-center">
            <li class="nav-item">
            <a class="nav-link <?= $page=='dashboard'?'active':'' ?>" href="?page=dashboard">Dashboard</a></li>
            <?php if($role == 'admin'): ?>
                <li class="nav-item">
                <a class="nav-link <?= $page=='user'?'active':'' ?>" href="?page=user">User</a></li>
                <li class="nav-item">
                <a class="nav-link <?= $page=='tarif'?'active':'' ?>" href="?page=tarif">Tarif</a></li>
                <li class="nav-item">
                <a class="nav-link <?= $page=='area'?'active':'' ?>" href="?page=area">Area</a></li>
                <li class="nav-item">
                <a class="nav-link <?= $page=='log'?'active':'' ?>" href="?page=log">Log</a></li>
            <?php endif; ?>
            	<li class="nav-item"></li>
			<?php if($role != 'owner'): ?>
    			<li class="nav-item">
        		<a class="nav-link <?= $page=='transaksi'?'active':'' ?>" href="?page=transaksi">Transaksi</a>
    			</li>
			<?php endif; ?>
            <?php if($role == 'owner'): ?>
                <li class="nav-item">
                <a class="nav-link <?= $page=='rekap'?'active':'' ?>" href="?page=rekap">Rekap</a></li>
            <?php endif; ?>
            <li class="nav-item">
            <a class="nav-link text-danger" href="?logout=1">Keluar</a></li>
        </ul>
    </div>

    <div class="content-area">
        <?php if($page == 'dashboard'): ?>
             <div class="row g-4 text-center">
                <div class="col-md-4"><div class="card p-4">
                <h6>Parkir Aktif</h6>
                <h3><?= mysqli_num_rows(mysqli_query($conn, "SELECT * FROM tb_transaksi WHERE status='masuk'")) ?></h3>
                </div></div>
                <div class="col-md-4">
                <div class="card p-4">
                <h6>Total Transaksi</h6>
                <h3><?= mysqli_num_rows(mysqli_query($conn, "SELECT * FROM tb_transaksi")) ?></h3>
                </div>
                </div>
                <div class="col-md-4"><div class="card p-4">
                <h6>Pendapatan Hari Ini</h6>
                <h3>Rp <?php $tp=mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(biaya_total) as total FROM tb_transaksi WHERE DATE(waktu_keluar)=CURDATE()")); echo number_format($tp['total']); ?></h3>
                </div>
                </div>
            </div>

        <?php elseif($page == 'user' && $role == 'admin'): 
            $e = null; if(isset($_GET['edit'])){ 
			$res = mysqli_query($conn, "SELECT * FROM tb_user WHERE id_user='$_GET[edit]'"); 
			$e = mysqli_fetch_assoc($res); } 
			?>
            <div class="card p-4 mb-4">
            <h6><?= $e ? 'Edit User' : 'Tambah User' ?></h6>
            <form method="POST" class="row g-2">
            <input type="hidden" name="aksi" value="<?= $e ? 'edit_user' : 'tambah_user' ?>">
            <input type="hidden" name="id" value="<?= isset($e['id_user'])?$e['id_user']:'' ?>">
            <div class="col-md-3">
  <input type="text" name="nama" class="form-control" placeholder="Nama" value="<?= isset($e['nama_lengkap'])?$e['nama_lengkap']:'' ?>" required>
     </div>
 <div class="col-md-3">
  <input type="text" name="user" class="form-control" placeholder="User" value="<?= isset($e['username'])?$e['username']:'' ?>" required>
       </div>
     <div class="col-md-2">
 <input type="text" name="pass" class="form-control" placeholder="Pass" value="<?= isset($e['password'])?$e['password']:'' ?>" required>
 </div>
 <div class="col-md-2">
 <select name="role_u" class="form-select"><option value="admin" <?=isset($e)&&$e['role']=='admin'?'selected':''?>>Admin</option>
 <option value="petugas" <?=isset($e)&&$e['role']=='petugas'?'selected':''?>>Petugas</option>
 <option value="owner" <?=isset($e)&&$e['role']=='owner'?'selected':''?>>Owner</option>
 </select>
 </div>
 <div class="col-md-2">
 <button type="submit" class="btn btn-primary w-100"><?= $e ? 'Update' : 'Simpan' ?></button>
 </div>
 </form>
 </div>
    <table class="table bg-white">
    <thead>
    <tr>
    <th>Nama</th>
    <th>User</th>
    <th>Role</th>
    <th>Aksi</th>
    </tr>
    </thead>
     <tbody>
	 <?php 
	 $q=mysqli_query($conn,"SELECT * FROM tb_user"); 
	 while($r=mysqli_fetch_assoc($q)): ?>
     <tr>
     <td><?=$r['nama_lengkap']?></td>
     <td><?=$r['username']?></td>
     <td><?=$r['role']?></td>
     <td><a href="?page=user&edit=<?=$r['id_user']?>" class="btn btn-sm btn-warning">
     <i class="bi bi-pencil"></i>
     </a> 
     <form method="POST" class="d-inline">
     <input type="hidden" name="aksi" value="hapus_user">
     <input type="hidden" name="id" value="<?=$r['id_user']?>">
     <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
     </form>
     </td>
     </tr>
	 <?php endwhile; ?>
     </tbody>
     </table>

    <?php elseif($page == 'tarif' && $role == 'admin'): 
    $e = null; if(isset($_GET['edit'])){ 
	$res = mysqli_query($conn, "SELECT * FROM tb_tarif WHERE id_tarif='$_GET[edit]'"); 
	$e = mysqli_fetch_assoc($res); } 
	?>
     <div class="card p-4 mb-4">
     <h6><?= $e ? 'Edit Tarif' : 'Tambah Tarif' ?></h6>
   <form method="POST" class="row g-2">
   <input type="hidden" name="aksi" value="<?= $e ? 'edit_tarif' : 'tambah_tarif' ?>">
   <input type="hidden" name="id" value="<?= isset($e['id_tarif'])?$e['id_tarif']:'' ?>">
   <div class="col-md-5">
   <input type="text" name="jenis" class="form-control" placeholder="Jenis Kendaraan" value="<?= isset($e['jenis_kendaraan'])?$e['jenis_kendaraan']:'' ?>" required>
   </div>
   <div class="col-md-5">
<input type="number" name="tarif" class="form-control" placeholder="Tarif/Jam" value="<?= isset($e['tarif_per_jam'])?$e['tarif_per_jam']:'' ?>" required>
   </div>
   <div class="col-md-2">
   <button type="submit" class="btn btn-primary w-100"><?= $e ? 'Update' : 'Tambah' ?></button>
   </div>
   </form>
   </div>
 <table class="table bg-white">
 <thead>
 <tr>
 <th>Jenis</th>
 <th>Tarif</th>
 <th>Aksi</th>
 </tr>
 </thead>
   <?php 
   $q=mysqli_query($conn,"SELECT * FROM tb_tarif"); 
   while($r=mysqli_fetch_assoc($q)): ?>
   <tr>
   <td><?=$r['jenis_kendaraan']?></td><td>Rp <?=number_format($r['tarif_per_jam'])?></td>
   <td><a href="?page=tarif&edit=<?=$r['id_tarif']?>" class="btn btn-sm btn-warning">
   <i class="bi bi-pencil"></i></a> 
   <form method="POST" class="d-inline"><input type="hidden" name="aksi" value="hapus_tarif">
   <input type="hidden" name="id" value="<?=$r['id_tarif']?>"><button class="btn btn-sm btn-danger">
   <i class="bi bi-trash"></i></button>
   </form></td>
   </tr>
   <?php endwhile; ?>
   </table>

   <?php elseif($page == 'area' && $role == 'admin'): 
   $e = null; if(isset($_GET['edit'])){ 
   $res = mysqli_query($conn, "SELECT * FROM tb_area_parkir WHERE id_area='$_GET[edit]'"); 
   $e = mysqli_fetch_assoc($res); } 
   ?>
   <div class="card p-4 mb-4">
   <h6><?= $e ? 'Edit Area' : 'Tambah Area' ?></h6>
 <form method="POST" class="row g-2">
 <input type="hidden" name="aksi" value="<?= $e ? 'edit_area' : 'tambah_area' ?>">
 <input type="hidden" name="id" value="<?= isset($e['id_area'])?$e['id_area']:'' ?>">
 <div class="col-md-5">
 <input type="text" name="nama_a" class="form-control" placeholder="Nama Area" value="<?= isset($e['nama_area'])?$e['nama_area']:'' ?>" required>
 </div>
 <div class="col-md-5">
 <input type="number" name="kapasitas" class="form-control" placeholder="Kapasitas" value="<?= isset($e['kapasitas'])?$e['kapasitas']:'' ?>" required>
 </div>
 <div class="col-md-2">
 <button type="submit" class="btn btn-primary w-100"><?= $e ? 'Update' : 'Simpan' ?></button>
 </div>
 </form>
 </div>
    <table class="table bg-white">
    <thead>
    <tr>
    <th>Area</th>
    <th>Slot</th>
    <th>Aksi</th>
    </tr>
    </thead>
   <?php $q=mysqli_query($conn,"SELECT * FROM tb_area_parkir"); 
   while($r=mysqli_fetch_assoc($q)): ?>
   <tr>
   <td><?=$r['nama_area']?></td>
   <td><?=$r['terisi']?> / <?=$r['kapasitas']?></td>
   <td><a href="?page=area&edit=<?=$r['id_area']?>" class="btn btn-sm btn-warning">
   <i class="bi bi-pencil"></i></a> 
   <form method="POST" class="d-inline">
   <input type="hidden" name="aksi" value="hapus_area">
   <input type="hidden" name="id" value="<?=$r['id_area']?>">
   <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
   </form>
   </td>
   </tr>
   <?php endwhile; ?>
   </table>
   
        <?php elseif($page == 'log' && $role == 'admin'): ?>
            <table class="table bg-white shadow-sm">
            <thead class="table-dark">
            <tr>
            <th>Waktu</th>
            <th>User</th>
            <th>Aktivitas</th>
            </tr>
            </thead>
            <tbody>
			<?php 
			$q=mysqli_query($conn,"SELECT l.*, u.nama_lengkap FROM tb_log_aktivitas l JOIN tb_user u ON l.id_user=u.id_user ORDER BY l.id_log DESC LIMIT 50"); while($r=mysqli_fetch_assoc($q)) 
			echo "<tr><td>$r[waktu_aktivitas]</td><td>$r[nama_lengkap]</td><td>$r[aktivitas]</td></tr>"; ?>
            </tbody>
            </table>
            
            
<?php elseif($page == 'transaksi' && $role != 'owner'): 
            $et = null; 
            if(isset($_GET['edit_p'])){ 
     $resP = mysqli_query($conn, "SELECT t.*, k.plat_nomor, k.warna, k.pemilik FROM tb_transaksi t JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan WHERE t.id_parkir='$_GET[edit_p]'"); 
   $et = mysqli_fetch_assoc($resP); 
   } 
?>
<div class="card p-4 mb-4 shadow-sm">
  <h5 class="fw-bold <?= $et ? 'text-warning' : 'text-success' ?> mb-3">
    <?= $et ? '<i class="bi bi-pencil-square"></i> Edit Data Parkir' : '<i class="bi bi-plus-circle"></i> Input Masuk Kendaraan' ?>
      </h5>
       <form method="POST" class="row g-3">
    <input type="hidden" name="aksi" value="<?= $et ? 'edit_parkir' : 'masuk_parkir' ?>">
  <?php if($et): ?>
     <input type="hidden" name="id_p" value="<?= $et['id_parkir'] ?>">
        <input type="hidden" name="id_k" value="<?= $et['id_kendaraan'] ?>">
           <?php endif; ?>

     <div class="col-md-2">
    <label class="small fw-bold">Plat</label>
    <input type="text" name="plat" class="form-control" value="<?= isset($et['plat_nomor']) ? $et['plat_nomor'] : '' ?>" placeholder="BK 123" required>
    </div>
       <div class="col-md-2">
   <label class="small fw-bold">Warna</label>
 <input type="text" name="warna" class="form-control" value="<?= isset($et['warna']) ? $et['warna'] : '' ?>" placeholder="Hitam">
     </div>
       <div class="col-md-2">
          <label class="small fw-bold">Pemilik</label>
     <input type="text" name="pemilik" class="form-control" value="<?= isset($et['pemilik']) ? $et['pemilik'] : '' ?>" placeholder="Nama">
       </div>
        <div class="col-md-2">
           <label class="small fw-bold">Jenis</label>
    <select name="id_t" class="form-select" onchange="document.getElementById('j_ken').value = this.options[this.selectedIndex].text.split(' ')[0];">
        <?php 
          $t=mysqli_query($conn,"SELECT * FROM tb_tarif"); 
            while($rt=mysqli_fetch_assoc($t)): 
              $selectedT = (isset($et['id_tarif']) && $et['id_tarif'] == $rt['id_tarif']) ? 'selected' : '';
         ?>
        <option value="<?= $rt['id_tarif'] ?>" <?= $selectedT ?>><?= $rt['jenis_kendaraan'] ?></option>
     <?php endwhile; ?>
   </select>
      <input type="hidden" name="jenis_k" id="j_ken" value="<?= isset($et['jenis_kendaraan']) ? $et['jenis_kendaraan'] : 'Motor' ?>">
        </div>
          <div class="col-md-2">
             <label class="small fw-bold">Area</label>
             <select name="id_a" class="form-select">
              <?php 
                $a=mysqli_query($conn,"SELECT * FROM tb_area_parkir"); 
                  while($ra=mysqli_fetch_assoc($a)): 
                    $selectedA = (isset($et['id_area']) && $et['id_area'] == $ra['id_area']) ? 'selected' : '';
              ?>
         <option value="<?= $ra['id_area'] ?>" <?= $selectedA ?>><?= $ra['nama_area'] ?> (<?= $ra['kapasitas']-$ra['terisi'] ?>)</option>
      <?php endwhile; ?>
    </select>
        </div>
          <div class="col-md-2 d-flex align-items-end">
             <button type="submit" class="btn <?= $et ? 'btn-warning' : 'btn-success' ?> w-100">
               <?= $et ? 'UPDATE' : 'MASUK' ?>
                  </button>
           <?php if($et): ?>
        <a href="?page=transaksi" class="btn btn-secondary ms-2">BATAL</a>
      <?php endif; ?>
    </div>
  </form>
</div>

            <table class="table table-hover bg-white shadow-sm align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Waktu Masuk</th>
                        <th>Plat</th>
                        <th>Pemilik</th>
                        <th>Jenis</th>
                        <th>Area</th>
                        <th>Status</th>
                        <th>Biaya</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $qh=mysqli_query($conn,"SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, k.warna, a.nama_area FROM tb_transaksi t JOIN tb_kendaraan k ON t.id_kendaraan=k.id_kendaraan JOIN tb_area_parkir a ON t.id_area=a.id_area WHERE DATE(t.waktu_masuk) = CURDATE() ORDER BY t.id_parkir DESC"); 
                    while($rh=mysqli_fetch_assoc($qh)): 
                    ?>
      <tr>
       <td><?= date('H:i', strtotime($rh['waktu_masuk'])) ?></td>
       <td><strong><?= $rh['plat_nomor'] ?></strong></td>
       <td><?= $rh['pemilik'] ?></td>
       <td><?= $rh['jenis_kendaraan'] ?></td>
       <td><?= $rh['nama_area'] ?></td>
       <td><span class="badge bg-<?= $rh['status']=='masuk'?'success':'secondary' ?>"><?= $rh['status'] ?></span></td>
       <td>Rp <?= number_format($rh['biaya_total']) ?></td>
       <td class="text-center">
          <?php if($rh['status'] == 'masuk'): ?>
             <form method="POST" class="d-inline">
                <input type="hidden" name="aksi" value="keluar_parkir">
                <input type="hidden" name="id_p" value="<?= $rh['id_parkir'] ?>">
                <button class="btn btn-sm btn-danger" title="Keluar">Keluar</button>
             </form>
     <a href="?page=transaksi&edit_p=<?= $rh['id_parkir'] ?>" class="btn btn-sm btn-warning" title="Edit">
     <i class="bi bi-pencil"></i></a>
    <?php endif; ?>
      <a href="?page=struk&id=<?= $rh['id_parkir'] ?>" class="btn btn-sm btn-info text-white" title="Print">
      <i class="bi bi-printer"></i></a>
   <form method="POST" class="d-inline" onsubmit="return confirm('Hapus permanen data ini? (Kuota parkir akan dikembalikan)')">
         <input type="hidden" name="aksi" value="hapus_parkir">
         <input type="hidden" name="id_p" value="<?= $rh['id_parkir'] ?>">
         <input type="hidden" name="id_k" value="<?= $rh['id_kendaraan'] ?>">
   <button type="submit" class="btn btn-sm btn-dark" title="Hapus"><i class="bi bi-trash"></i></button>
      </form>
         </td>
        </tr>
       <?php endwhile; ?>
   </tbody>
 </table>

  <?php elseif($page == 'struk'): 
   $id = isset($_GET['id'])?$_GET['id']:0; $sql="SELECT t.*, k.*, tr.tarif_per_jam FROM tb_transaksi t JOIN tb_kendaraan k ON t.id_kendaraan=k.id_kendaraan JOIN tb_tarif tr ON t.id_tarif=tr.id_tarif WHERE t.id_parkir='$id'"; if($id==0) $sql.=" ORDER BY id_parkir DESC LIMIT 1"; $ds=mysqli_fetch_assoc(mysqli_query($conn,$sql)); 
   ?>
<div class="text-center bg-white p-5 border mx-auto shadow-sm" style="max-width: 400px;">
<h4>STRUK PARKIR</h4>
<hr>
<h2><?=$ds['plat_nomor']?></h2>
<p><?=$ds['jenis_kendaraan']?> | <?=$ds['warna']?></p><p>Masuk: <?=$ds['waktu_masuk']?>
<br>Keluar: <?=$ds['waktu_keluar']?></p><hr>
<h5>Durasi: <?=$ds['durasi_jam']?> Jam</h5>
<h4>Total: Rp <?=number_format($ds['biaya_total'])?></h4><hr>
<button onclick="window.print()" class="btn btn-primary no-print w-100 mb-2">CETAK</button>
<a href="?page=transaksi" class="btn btn-outline-secondary no-print w-100">Kembali</a>
</div>

        <?php elseif($page == 'rekap' && $role == 'owner'): ?>
  <div class="card p-4 mb-4 no-print"><form method="GET" class="row g-2">
  <input type="hidden" name="page" value="rekap">
  <div class="col-md-5">
  <input type="date" name="tgl" class="form-control" value="<?=isset($_GET['tgl'])?$_GET['tgl']:date('Y-m-d')?>">
  </div>
  <div class="col-md-3">
  <button type="submit" class="btn btn-dark w-100">Filter</button>
  </div>
  <div class="col-md-4">
  <button onclick="window.print()" type="button" class="btn btn-primary w-100">
  <i class="bi bi-printer"></i> Laporan</button>
  </div>
  </form>
  </div>
     <div class="card p-4 bg-white">
     <div class="text-center mb-4 only-print">
     <h3>LAPORAN HARIAN</h3><hr>
     </div><table class="table table-bordered">
        <thead class="table-dark"><tr><th>Masuk</th><th>Keluar</th><th>Plat</th><th>Durasi</th><th>Biaya</th></tr></thead>
        <tbody>
		<?php 
		$tgl=isset($_GET['tgl'])?$_GET['tgl']:date('Y-m-d'); $total=0; $qr=mysqli_query($conn,"SELECT t.*, k.plat_nomor FROM tb_transaksi t JOIN tb_kendaraan k ON t.id_kendaraan=k.id_kendaraan WHERE DATE(t.waktu_masuk)='$tgl'"); 
  while($row=mysqli_fetch_assoc($qr)): $total+=$row['biaya_total']; ?>
   <tr>
   <td><?=$row['waktu_masuk']?></td><td><?=$row['waktu_keluar']?></td>
   <td><strong><?=$row['plat_nomor']?></strong></td>
   <td><?=$row['durasi_jam']?> Jam</td>
   <td class="text-end">Rp <?=number_format($row['biaya_total'])?></td>
   </tr>
   <?php endwhile; ?>
   </tbody>
   <tfoot class="table-light">
   <tr>
   <th colspan="4" class="text-end">TOTAL PENDAPATAN:</th>
   <th class="text-end text-primary">Rp <?=number_format($total)?></th>
   </tr>
   </tfoot>
   </table>
   </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>