<?php
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../vendor/autoload.php';

use SleekDB\Store;

$databaseDir = __DIR__ . '/../database';
if (!is_dir($databaseDir)) {
    mkdir($databaseDir, 0777, true);
}

$voteStore = new Store('votes', $databaseDir, [
    'auto_cache' => false
]);

// 1. Ambil data santri dari Data Santri.json
$santriJsonFile = __DIR__ . '/../Data Santri.json';
$allSantri = file_exists($santriJsonFile) ? json_decode(file_get_contents($santriJsonFile), true) : [];
if (!is_array($allSantri)) {
    $allSantri = [];
}

// 2. Ambil data voting yang sudah tersimpan di SleekDB
$allVotes = $voteStore->findAll();
$votedNis = array_map(function($v) {
    return (string)$v['nis'];
}, $allVotes);

// Handle Reset Voting (untuk testing jika diperlukan oleh admin)
if (isset($_GET['action']) && $_GET['action'] === 'reset_votes') {
    foreach ($allVotes as $v) {
        if (isset($v['_id'])) {
            $voteStore->deleteById($v['_id']);
        }
    }
    header('Location: /voting/');
    exit;
}

// 3. Filter santri yang belum memilih
$availableSantri = array_filter($allSantri, function($santri) use ($votedNis) {
    return !in_array((string)$santri['NIS'], $votedNis);
});

// Urutkan nama santri sesuai alfabet
usort($availableSantri, function($a, $b) {
    return strcasecmp($a['Nama Sesuai KK'], $b['Nama Sesuai KK']);
});

// Daftar kandidat Musyrif (diurutkan namanya: Hanif -> Nawwar -> Zakki)
$musyrifList = [
    [
        'nama' => 'Ustadz Hanif',
        'foto' => '/Foto%20Musyrif/Ustadz%20Hanif.jpg',
        'kategori' => 'Musyrif'
    ],
    [
        'nama' => 'Ustadz Nawwar',
        'foto' => '/Foto%20Musyrif/Ustadz%20Nawwar.jpg',
        'kategori' => 'Musyrif'
    ],
    [
        'nama' => 'Ustadz Zakki',
        'foto' => '/Foto%20Musyrif/Ustadz%20Zakki.jpg',
        'kategori' => 'Musyrif'
    ],
];

// Daftar kandidat Musyrifah (diurutkan namanya: Marwa -> Rahmah -> Rania)
$musyrifahList = [
    [
        'nama' => 'Ustadzah Marwa',
        'foto' => '/Foto%20Musyrif/Ustadzah%20Marwa.jpg',
        'kategori' => 'Musyrifah'
    ],
    [
        'nama' => 'Ustadzah Rahmah',
        'foto' => '/Foto%20Musyrif/Ustadzah%20Rahmah.jpg',
        'kategori' => 'Musyrifah'
    ],
    [
        'nama' => 'Ustadzah Rania',
        'foto' => '/Foto%20Musyrif/Ustadzah%20Rania.jpg',
        'kategori' => 'Musyrifah'
    ],
];

// Handle POST Submit Voting
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isJson = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
    $input = $isJson ? json_decode(file_get_contents('php://input'), true) : $_POST;

    $nis = trim($input['nis'] ?? '');
    $kandidat = trim($input['kandidat'] ?? '');
    $alasan = trim($input['alasan'] ?? '');

    if (empty($nis) || empty($kandidat) || empty($alasan)) {
        $errorMessage = 'Mohon lengkapi semua formulir: nama pemilih, kandidat yang dipilih, dan alasan.';
        if ($isJson) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        }
    } else {
        // Validasi batas maksimal 30 kata
        $wordArray = preg_split('/\s+/', $alasan, -1, PREG_SPLIT_NO_EMPTY);
        if (count($wordArray) > 30) {
            $errorMessage = 'Alasan maksimal 30 kata. Saat ini terdapat ' . count($wordArray) . ' kata.';
            if ($isJson) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $errorMessage]);
                exit;
            }
        } else {
        // Cari data santri berdasarkan NIS
        $selectedSantri = null;
        foreach ($allSantri as $s) {
            if ((string)$s['NIS'] === $nis) {
                $selectedSantri = $s;
                break;
            }
        }

        if (!$selectedSantri) {
            $errorMessage = 'Data santri tidak ditemukan.';
            if ($isJson) {
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => $errorMessage]);
                exit;
            }
        } elseif (in_array($nis, $votedNis)) {
            $errorMessage = 'Santri ini sudah pernah memberikan suaranya.';
            if ($isJson) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $errorMessage]);
                exit;
            }
        } else {
            // Validasi kecocokan jenis kelamin & kandidat
            $isLaki = strcasecmp(trim($selectedSantri['Jenis Kelamin']), 'laki-laki') === 0;
            $allowedCandidates = $isLaki
                ? array_column($musyrifList, 'nama')
                : array_column($musyrifahList, 'nama');

            if (!in_array($kandidat, $allowedCandidates)) {
                $errorMessage = 'Kandidat yang dipilih tidak valid untuk kategori Anda.';
                if ($isJson) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $errorMessage]);
                    exit;
                }
            } else {
                // Simpan ke SleekDB
                $voteRecord = [
                    'nis' => $nis,
                    'nama_pemilih' => $selectedSantri['Nama Sesuai KK'],
                    'nama_panggilan' => $selectedSantri['Nama Panggilan'] ?? '',
                    'jenis_kelamin' => $selectedSantri['Jenis Kelamin'],
                    'kategori' => $isLaki ? 'Musyrif' : 'Musyrifah',
                    'kandidat' => $kandidat,
                    'alasan' => $alasan,
                    'waktu_voting' => date('Y-m-d H:i:s')
                ];

                $voteStore->insert($voteRecord);

                if ($isJson) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Terima kasih sudah memilih!',
                        'nama' => $selectedSantri['Nama Sesuai KK'],
                        'kandidat' => $kandidat
                    ]);
                    exit;
                } else {
                    header('Location: /voting/?success=1&nama=' . urlencode($selectedSantri['Nama Sesuai KK']) . '&kandidat=' . urlencode($kandidat));
                    exit;
                }
            }
        }
    }
}
}

// Statistik Singkat
$totalSantri = count($allSantri);
$totalMemilih = count($allVotes);
$totalBelum = count($availableSantri);
$isSuccessParam = isset($_GET['success']) && $_GET['success'] == '1';
$successNama = $_GET['nama'] ?? '';
$successKandidat = $_GET['kandidat'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Voting Pemilihan Musyrif & Musyrifah</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .candidate-card {
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .candidate-card:hover {
      transform: translateY(-4px);
    }
    .candidate-card.selected {
      border-color: #059669;
      background-color: #ecfdf5;
      box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.25), 0 8px 10px -6px rgba(5, 150, 105, 0.2);
    }
    .candidate-card.selected .check-badge {
      display: flex;
    }
    .candidate-card.selected img {
      border-color: #059669;
      transform: scale(1.02);
    }
  </style>
</head>
<body class="bg-gradient-to-br from-emerald-50 via-teal-50/50 to-slate-100 min-h-screen text-slate-800 antialiased py-8 px-4 sm:px-6 lg:px-8">

  <!-- Container Utama -->
  <div class="max-w-4xl mx-auto">

    <!-- Header Aplikasi -->
    <header class="text-center mb-8">
      
      <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
        E-Voting Musyrif & Musyrifah
      </h1>
      <p class="text-slate-600 mt-2 text-sm sm:text-base max-w-xl mx-auto">
        Tentukan pilihan Anda untuk Ustadz atau Ustadzah teladan yang akan membimbing dan mendampingi Anda.
      </p>

      <!-- Indikator Partisipasi -->
      <div class="flex items-center justify-center gap-4 mt-4 text-xs sm:text-sm font-medium text-slate-500">
        <span class="flex items-center gap-1.5 bg-white px-3 py-1 rounded-full shadow-sm border border-slate-200">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
          Sudah Memilih: <strong class="text-slate-800"><?= $totalMemilih ?></strong>
        </span>
        <span class="flex items-center gap-1.5 bg-white px-3 py-1 rounded-full shadow-sm border border-slate-200">
          <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
          Belum Memilih: <strong class="text-slate-800"><?= $totalBelum ?></strong>
        </span>
      </div>
    </header>

    <?php if (!empty($errorMessage)): ?>
      <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 shadow-sm">
        <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div class="text-sm font-medium"><?= htmlspecialchars($errorMessage) ?></div>
      </div>
    <?php endif; ?>

    <?php if (empty($availableSantri)): ?>
      <!-- Tampilan Jika Semua Santri Sudah Memilih -->
      <div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 p-8 sm:p-12 text-center">
        <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-5 shadow-inner">
          <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-slate-800 mb-2">Alhamdulillah, Semua Santri Telah Memilih!</h2>
        <p class="text-slate-600 max-w-md mx-auto mb-6 text-sm sm:text-base">
          Seluruh <strong><?= $totalSantri ?></strong> santri telah memberikan suaranya. Proses pemungutan suara telah selesai dengan sukses.
        </p>
        <div class="inline-flex gap-3">
          <a href="/voting/?view=rekap" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-2.5 rounded-xl shadow transition">
            Lihat Rekap Hasil
          </a>
        </div>
      </div>
    <?php else: ?>

      <!-- Formulir Voting -->
      <form id="votingForm" method="POST" action="/voting/" class="bg-white rounded-2xl shadow-xl border border-slate-200/80 p-6 sm:p-8 space-y-8">

        <!-- LANGKAH 1: Pilih Nama Pemilih -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <span class="w-7 h-7 rounded-full bg-emerald-600 text-white font-bold text-sm flex items-center justify-center shadow-sm">1</span>
            <label for="voterSelect" class="text-lg font-bold text-slate-900">
              Pilih Nama Pemilih
            </label>
          </div>
          <p class="text-slate-500 text-xs sm:text-sm mb-3">
            Pilih nama Anda dari daftar berikut. Nama yang sudah selesai memilih tidak akan tampil di daftar ini.
          </p>

          <div class="relative">
            <select id="voterSelect" name="nis" required
              class="w-full bg-slate-50 border-2 border-slate-200 text-slate-800 text-base rounded-xl focus:ring-4 focus:ring-emerald-500/20 focus:border-emerald-500 block p-3.5 pr-10 transition font-medium appearance-none cursor-pointer">
              <option value="" data-gender="">-- Silakan Pilih Nama Anda --</option>
              <?php foreach ($availableSantri as $santri): ?>
                <?php
                  $genderClean = strcasecmp(trim($santri['Jenis Kelamin']), 'laki-laki') === 0 ? 'Laki-laki' : 'Perempuan';
                  $panggilan = !empty($santri['Nama Panggilan']) ? ' (' . $santri['Nama Panggilan'] . ')' : '';
                  $icon = $genderClean === 'Laki-laki' ? '👦' : '👧';
                ?>
                <option
                  value="<?= htmlspecialchars($santri['NIS']) ?>"
                  data-gender="<?= $genderClean ?>"
                  data-name="<?= htmlspecialchars($santri['Nama Sesuai KK']) ?>">
                  <?= $icon ?> <?= htmlspecialchars($santri['Nama Sesuai KK'] . $panggilan) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-500">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </div>
          </div>

          <!-- Info Badge Jenis Kelamin -->
          <div id="voterInfoBadge" class="hidden mt-3 p-3 bg-emerald-50/80 border border-emerald-200 rounded-xl items-center justify-between text-sm">
            <div class="flex items-center gap-2">
              <span id="genderIcon" class="text-xl">👦</span>
              <div>
                <span class="text-slate-500 text-xs">Kategori Pemilih:</span>
                <div id="voterCategoryText" class="font-bold text-emerald-800">Santri Ikhwan &rarr; Memilih Musyrif</div>
              </div>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-md">Terverifikasi</span>
          </div>
        </div>

        <!-- Placeholder Sebelum Pemilih Dipilih -->
        <div id="placeholderStep" class="py-12 border-2 border-dashed border-slate-200 rounded-xl text-center bg-slate-50/50">
          <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
          </div>
          <h3 class="font-semibold text-slate-700">Daftar Kandidat Belum Terbuka</h3>
          <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-sm mx-auto">
            Silakan pilih nama pemilih pada langkah 1 di atas terlebih dahulu untuk menampilkan Musyrif / Musyrifah yang sesuai.
          </p>
        </div>

        <!-- LANGKAH 2: Pilih Kandidat (Dinamis Musyrif / Musyrifah) -->
        <div id="candidateSection" class="hidden space-y-4">
          <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-emerald-600 text-white font-bold text-sm flex items-center justify-center shadow-sm">2</span>
            <h2 id="candidateSectionTitle" class="text-lg font-bold text-slate-900">
              Pilih Musyrif / Musyrifah
            </h2>
          </div>
          <p class="text-slate-500 text-xs sm:text-sm">
            Klik pada salah satu kartu kandidat untuk menentukan pilihan Anda.
          </p>

          <!-- List Kandidat Musyrif (Laki-laki) -->
          <div id="musyrifContainer" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 pt-2">
            <?php foreach ($musyrifList as $index => $candidate): ?>
              <label class="candidate-card relative flex flex-col items-center bg-white border-2 border-slate-200 rounded-2xl p-4 cursor-pointer hover:border-emerald-300 transition group select-none">
                <input type="radio" name="kandidat" value="<?= htmlspecialchars($candidate['nama']) ?>" class="sr-only candidate-radio" disabled>

                <!-- Check Badge -->
                <div class="check-badge hidden absolute top-3 right-3 w-7 h-7 bg-emerald-600 text-white rounded-full items-center justify-center shadow-md">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>

                <!-- Foto Kandidat -->
                <div class="w-32 h-32 sm:w-40 sm:h-40 rounded-xl overflow-hidden mb-3 border-2 border-slate-200 bg-slate-100 group-hover:border-emerald-400 transition shadow-sm">
                  <img
                    src="<?= $candidate['foto'] ?>"
                    alt="<?= htmlspecialchars($candidate['nama']) ?>"
                    class="w-full h-full object-cover transition duration-300"
                    loading="lazy">
                </div>

                <!-- Nama & Label -->
                <h3 class="font-bold text-slate-900 text-center text-base sm:text-lg group-hover:text-emerald-700 transition">
                  <?= htmlspecialchars($candidate['nama']) ?>
                </h3>
                

                <div class="mt-3 w-full py-1.5 text-center text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 group-hover:border-emerald-500 group-hover:bg-emerald-50 group-hover:text-emerald-700 transition select-button">
                  Pilih Beliau
                </div>
              </label>
            <?php endforeach; ?>
          </div>

          <!-- List Kandidat Musyrifah (Perempuan) -->
          <div id="musyrifahContainer" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 pt-2">
            <?php foreach ($musyrifahList as $index => $candidate): ?>
              <label class="candidate-card relative flex flex-col items-center bg-white border-2 border-slate-200 rounded-2xl p-4 cursor-pointer hover:border-emerald-300 transition group select-none">
                <input type="radio" name="kandidat" value="<?= htmlspecialchars($candidate['nama']) ?>" class="sr-only candidate-radio" disabled>

                <!-- Check Badge -->
                <div class="check-badge hidden absolute top-3 right-3 w-7 h-7 bg-emerald-600 text-white rounded-full items-center justify-center shadow-md">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>

                <!-- Foto Kandidat -->
                <div class="w-32 h-32 sm:w-40 sm:h-40 rounded-xl overflow-hidden mb-3 border-2 border-slate-200 bg-slate-100 group-hover:border-emerald-400 transition shadow-sm">
                  <img
                    src="<?= $candidate['foto'] ?>"
                    alt="<?= htmlspecialchars($candidate['nama']) ?>"
                    class="w-full h-full object-cover transition duration-300"
                    loading="lazy">
                </div>

                <!-- Nama & Label -->
                <h3 class="font-bold text-slate-900 text-center text-base sm:text-lg group-hover:text-emerald-700 transition">
                  <?= htmlspecialchars($candidate['nama']) ?>
                </h3>
               
                <div class="mt-3 w-full py-1.5 text-center text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 group-hover:border-teal-500 group-hover:bg-teal-50 group-hover:text-teal-700 transition select-button">
                  Pilih Beliau
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- LANGKAH 3: Alasan Memilih (Textarea) -->
        <div id="reasonSection" class="hidden">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
              <span class="w-7 h-7 rounded-full bg-emerald-600 text-white font-bold text-sm flex items-center justify-center shadow-sm">3</span>
              <label for="alasanTextarea" class="text-lg font-bold text-slate-900">
                Alasan Memilih
              </label>
            </div>
            <div id="wordCountBadge" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200 transition">
              <span id="wordCount">0</span> / 30 kata
            </div>
          </div>
          <p class="text-slate-500 text-xs sm:text-sm mb-3">
            Tuliskan alasan mengapa Anda memilih Ustadz / Ustadzah tersebut (maksimal 30 kata).
          </p>

          <textarea
            id="alasanTextarea"
            name="alasan"
            rows="4"
            required
            placeholder="Contoh: Beliau sangat telaten, ramah, dan membimbing hafalan Al-Qur'an dengan sabar..."
            class="w-full bg-slate-50 border-2 border-slate-200 text-slate-800 text-sm sm:text-base rounded-xl focus:ring-4 focus:ring-emerald-500/20 focus:border-emerald-500 block p-3.5 transition placeholder-slate-400"></textarea>
          <div id="wordLimitWarning" class="hidden mt-2 text-xs text-rose-600 font-bold flex items-center gap-1.5">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span>Alasan melebihi batas maksimal 30 kata! Mohon persingkat sebelum mengirim.</span>
          </div>
        </div>

        <!-- Tombol Kirim -->
        <div id="submitSection" class="hidden pt-4 border-t border-slate-100">
          <button
            type="submit"
            id="submitBtn"
            class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold text-base sm:text-lg py-4 px-6 rounded-xl shadow-lg hover:shadow-xl transition transform active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </svg>
            <span>Kirim Pilihan Saya</span>
          </button>
        </div>

      </form>
    <?php endif; ?>

    <!-- Modal Ucapan Terima Kasih & Redirect -->
    <div id="thankYouModal" class="<?= $isSuccessParam ? 'flex' : 'hidden' ?> fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm items-center justify-center p-4">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 text-center shadow-2xl border border-emerald-100 transform transition-all animate-bounce-short">

        <!-- Checkmark Animasi -->
        <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-5 shadow-inner">
          <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
          </svg>
        </div>

        <h3 class="text-2xl font-extrabold text-slate-900 mb-2">
          Terima Kasih Sudah Memilih!
        </h3>

        <p class="text-slate-600 text-sm sm:text-base mb-6">
          Suara dari <strong id="modalVoterName" class="text-emerald-700"><?= htmlspecialchars($successNama ?: 'Santri') ?></strong>
          <?php if (!empty($successKandidat)): ?>
            untuk <strong id="modalCandidateName" class="text-slate-800"><?= htmlspecialchars($successKandidat) ?></strong>
          <?php else: ?>
            <span id="modalCandidatePart">telah berhasil disimpan dengan aman</span>
          <?php endif; ?>.
        </p>

        <!-- Timer Redirect -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 mb-6">
          <p class="text-xs sm:text-sm text-slate-500 mb-1">
            Mempersiapkan halaman untuk pemilih berikutnya...
          </p>
          <div class="text-lg font-bold text-emerald-600">
            Mengalihkan dalam <span id="countdownTimer" class="text-2xl font-extrabold text-slate-900">3</span> detik
          </div>
          <!-- Progress Bar -->
          <div class="w-full bg-slate-200 h-2 rounded-full mt-3 overflow-hidden">
            <div id="progressBar" class="bg-emerald-500 h-full w-full transition-all duration-1000 ease-linear"></div>
          </div>
        </div>

        <button
          type="button"
          id="btnNextVoter"
          class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 px-6 rounded-xl shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
          <span>Pemilih Berikutnya Sekarang</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Tombol & Bagian Rekap Hasil (Opsional di bagian bawah) -->
    <footer class="mt-12 text-center border-t border-slate-200 pt-6 text-xs text-slate-500">
      <div class="flex items-center justify-center gap-4 mb-2">
        <a href="#" class="text-emerald-700 hover:text-emerald-800 font-semibold underline underline-offset-2">
          Lihat Rekap Hasil Pemilihan
        </a>
        <span>&bull;</span>
        <a href="#" onclick="return confirm('Apakah Anda yakin ingin mereset seluruh data voting?')" class="text-slate-400 hover:text-rose-600 transition">
          Reset Data Voting
        </a>
      </div>
      <p>&copy; 2026 E-Voting Santri &bull; Disimpan aman dengan SleekDB</p>
    </footer>

    <?php if (isset($_GET['view']) && $_GET['view'] === 'rekap'): ?>
      <!-- Rekap Modal / Section -->
      <div class="mt-8 bg-white rounded-2xl shadow-lg border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
          <h3 class="text-lg font-bold text-slate-800">Rekapitulasi Suara Terkumpul</h3>
          <a href="/voting/" class="text-xs bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg text-slate-600 font-medium">Tutup Rekap</a>
        </div>

        <?php if (empty($allVotes)): ?>
          <p class="text-slate-500 text-sm py-4 text-center">Belum ada suara yang masuk.</p>
        <?php else: ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <!-- Suara Musyrif -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
              <h4 class="font-bold text-sm text-slate-700 mb-2">Perolehan Suara Musyrif</h4>
              <?php
                $musyrifCounts = ['Ustadz Hanif' => 0, 'Ustadz Nawwar' => 0, 'Ustadz Zakki' => 0];
                foreach ($allVotes as $v) {
                  if (isset($musyrifCounts[$v['kandidat']])) {
                    $musyrifCounts[$v['kandidat']]++;
                  }
                }
              ?>
              <ul class="space-y-1.5 text-sm">
                <?php foreach ($musyrifCounts as $nama => $count): ?>
                  <li class="flex justify-between items-center bg-white p-2 rounded border border-slate-100">
                    <span><?= htmlspecialchars($nama) ?></span>
                    <strong class="text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded"><?= $count ?> suara</strong>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <!-- Suara Musyrifah -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
              <h4 class="font-bold text-sm text-slate-700 mb-2">Perolehan Suara Musyrifah</h4>
              <?php
                $musyrifahCounts = ['Ustadzah Marwa' => 0, 'Ustadzah Rahmah' => 0, 'Ustadzah Rania' => 0];
                foreach ($allVotes as $v) {
                  if (isset($musyrifahCounts[$v['kandidat']])) {
                    $musyrifahCounts[$v['kandidat']]++;
                  }
                }
              ?>
              <ul class="space-y-1.5 text-sm">
                <?php foreach ($musyrifahCounts as $nama => $count): ?>
                  <li class="flex justify-between items-center bg-white p-2 rounded border border-slate-100">
                    <span><?= htmlspecialchars($nama) ?></span>
                    <strong class="text-teal-600 bg-teal-50 px-2 py-0.5 rounded"><?= $count ?> suara</strong>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>

          <!-- Tabel Rincian -->
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-100 text-slate-700">
                  <th class="p-2.5 rounded-l">No</th>
                  <th class="p-2.5">Nama Santri</th>
                  <th class="p-2.5">Kategori</th>
                  <th class="p-2.5">Kandidat Terpilih</th>
                  <th class="p-2.5">Alasan</th>
                  <th class="p-2.5 rounded-r">Waktu</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-slate-600">
                <?php foreach ($allVotes as $i => $v): ?>
                  <tr class="hover:bg-slate-50">
                    <td class="p-2.5 font-medium"><?= $i + 1 ?></td>
                    <td class="p-2.5 font-semibold text-slate-800"><?= htmlspecialchars($v['nama_pemilih']) ?></td>
                    <td class="p-2.5"><?= htmlspecialchars($v['kategori'] ?? '') ?></td>
                    <td class="p-2.5 font-bold text-emerald-700"><?= htmlspecialchars($v['kandidat']) ?></td>
                    <td class="p-2.5 max-w-xs truncate" title="<?= htmlspecialchars($v['alasan'] ?? '') ?>"><?= htmlspecialchars($v['alasan'] ?? '') ?></td>
                    <td class="p-2.5 text-slate-400"><?= htmlspecialchars($v['waktu_voting'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>

  <!-- Client-side Logic -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const voterSelect = document.getElementById('voterSelect');
      const voterInfoBadge = document.getElementById('voterInfoBadge');
      const voterCategoryText = document.getElementById('voterCategoryText');
      const genderIcon = document.getElementById('genderIcon');
      const placeholderStep = document.getElementById('placeholderStep');
      const candidateSection = document.getElementById('candidateSection');
      const candidateSectionTitle = document.getElementById('candidateSectionTitle');
      const musyrifContainer = document.getElementById('musyrifContainer');
      const musyrifahContainer = document.getElementById('musyrifahContainer');
      const reasonSection = document.getElementById('reasonSection');
      const submitSection = document.getElementById('submitSection');
      const votingForm = document.getElementById('votingForm');
      const submitBtn = document.getElementById('submitBtn');

      const thankYouModal = document.getElementById('thankYouModal');
      const modalVoterName = document.getElementById('modalVoterName');
      const modalCandidateName = document.getElementById('modalCandidateName');
      const countdownTimer = document.getElementById('countdownTimer');
      const progressBar = document.getElementById('progressBar');
      const btnNextVoter = document.getElementById('btnNextVoter');

      const alasanTextarea = document.getElementById('alasanTextarea');
      const wordCount = document.getElementById('wordCount');
      const wordCountBadge = document.getElementById('wordCountBadge');
      const wordLimitWarning = document.getElementById('wordLimitWarning');

      let currentGender = '';

      function countWords(str) {
        if (!str) return 0;
        const matches = str.trim().match(/\S+/g);
        return matches ? matches.length : 0;
      }

      function updateWordCount() {
        if (!alasanTextarea) return;
        const count = countWords(alasanTextarea.value);
        if (wordCount) wordCount.textContent = count;

        if (count > 30) {
          if (wordCountBadge) {
            wordCountBadge.className = 'text-xs font-bold px-2.5 py-1 rounded-lg bg-rose-100 text-rose-700 border border-rose-300 transition';
          }
          if (wordLimitWarning) wordLimitWarning.classList.remove('hidden');
          alasanTextarea.classList.add('border-rose-400', 'focus:border-rose-500', 'focus:ring-rose-500/20');
          alasanTextarea.classList.remove('border-slate-200', 'focus:border-emerald-500', 'focus:ring-emerald-500/20');
        } else {
          if (wordCountBadge) {
            wordCountBadge.className = 'text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200 transition';
          }
          if (wordLimitWarning) wordLimitWarning.classList.add('hidden');
          alasanTextarea.classList.remove('border-rose-400', 'focus:border-rose-500', 'focus:ring-rose-500/20');
          alasanTextarea.classList.add('border-slate-200', 'focus:border-emerald-500', 'focus:ring-emerald-500/20');
        }
      }

      if (alasanTextarea) {
        alasanTextarea.addEventListener('input', updateWordCount);
      }

      // Fungsi Redirect Countdown
      function startRedirectCountdown() {
        let seconds = 3;
        if (countdownTimer) countdownTimer.textContent = seconds;
        if (progressBar) progressBar.style.width = '100%';

        const interval = setInterval(() => {
          seconds--;
          if (countdownTimer) countdownTimer.textContent = seconds;
          if (progressBar) progressBar.style.width = (seconds / 3 * 100) + '%';

          if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = '/voting/';
          }
        }, 1000);

        if (btnNextVoter) {
          btnNextVoter.addEventListener('click', function() {
            clearInterval(interval);
            window.location.href = '/voting/';
          });
        }
      }

      // Jika dibuka dengan parameter ?success=1
      <?php if ($isSuccessParam): ?>
        startRedirectCountdown();
      <?php endif; ?>

      if (!voterSelect) return;

      // Event saat nama pemilih dipilih
      voterSelect.addEventListener('change', function() {
        const selectedOption = voterSelect.options[voterSelect.selectedIndex];
        const gender = (selectedOption.getAttribute('data-gender') || '').trim();
        const voterName = selectedOption.getAttribute('data-name') || '';

        // Reset semua pilihan kartu kandidat
        document.querySelectorAll('.candidate-card').forEach(card => {
          card.classList.remove('selected');
          const btn = card.querySelector('.select-button');
          if (btn) btn.textContent = 'Pilih Beliau';
        });
        document.querySelectorAll('.candidate-radio').forEach(radio => {
          radio.checked = false;
          radio.disabled = true;
        });

        if (!gender) {
          // Tidak ada pemilih yang dipilih
          placeholderStep.classList.remove('hidden');
          candidateSection.classList.add('hidden');
          reasonSection.classList.add('hidden');
          submitSection.classList.add('hidden');
          voterInfoBadge.classList.add('hidden');
          currentGender = '';
          return;
        }

        currentGender = gender;
        placeholderStep.classList.add('hidden');
        candidateSection.classList.remove('hidden');
        reasonSection.classList.remove('hidden');
        submitSection.classList.remove('hidden');
        voterInfoBadge.classList.remove('hidden');
        voterInfoBadge.classList.add('flex');

        if (gender.toLowerCase() === 'laki-laki') {
          // Santri Laki-laki -> Memilih Musyrif
          candidateSectionTitle.textContent = 'Langkah 2: Pilih Musyrif Pilihan Anda';
          voterCategoryText.textContent = 'Santri Ikhwan (Laki-laki) → Memilih Musyrif';
          genderIcon.textContent = '👦';

          musyrifContainer.classList.remove('hidden');
          musyrifahContainer.classList.add('hidden');

          // Aktifkan radio buttons musyrif
          musyrifContainer.querySelectorAll('.candidate-radio').forEach(r => r.disabled = false);
        } else {
          // Santri Perempuan -> Memilih Musyrifah
          candidateSectionTitle.textContent = 'Langkah 2: Pilih Musyrifah Pilihan Anda';
          voterCategoryText.textContent = 'Santriwati Akhwat (Perempuan) → Memilih Musyrifah';
          genderIcon.textContent = '👧';

          musyrifContainer.classList.add('hidden');
          musyrifahContainer.classList.remove('hidden');

          // Aktifkan radio buttons musyrifah
          musyrifahContainer.querySelectorAll('.candidate-radio').forEach(r => r.disabled = false);
        }
      });

      // Event saat kartu kandidat diklik
      document.querySelectorAll('.candidate-card').forEach(card => {
        card.addEventListener('click', function(e) {
          const radio = card.querySelector('.candidate-radio');
          if (!radio || radio.disabled) return;

          // Hapus kelas selected dari kontainer yang sama
          const parentContainer = card.closest('#musyrifContainer, #musyrifahContainer');
          if (parentContainer) {
            parentContainer.querySelectorAll('.candidate-card').forEach(c => {
              c.classList.remove('selected');
              const btn = c.querySelector('.select-button');
              if (btn) btn.textContent = 'Pilih Beliau';
            });
          }

          // Pilih kandidat ini
          radio.checked = true;
          card.classList.add('selected');
          const btn = card.querySelector('.select-button');
          if (btn) btn.textContent = '✓ Terpilih';
        });
      });

      // Handle submit form dengan AJAX Fetch agar animasi ucapan terima kasih mulus
      votingForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const nis = voterSelect.value;
        const selectedRadio = document.querySelector('.candidate-radio:checked:not(:disabled)');
        const alasan = document.getElementById('alasanTextarea').value.trim();

        if (!nis) {
          alert('Mohon pilih nama pemilih terlebih dahulu.');
          voterSelect.focus();
          return;
        }

        if (!selectedRadio) {
          alert('Mohon pilih salah satu kandidat terlebih dahulu.');
          return;
        }

        if (!alasan) {
          alert('Mohon isi alasan Anda memilih kandidat tersebut.');
          alasanTextarea.focus();
          return;
        }

        const wordsNum = countWords(alasan);
        if (wordsNum > 30) {
          alert('Alasan maksimal 30 kata. Saat ini terdapat ' + wordsNum + ' kata. Mohon persingkat alasan Anda sebelum mengirim.');
          alasanTextarea.focus();
          return;
        }

        const kandidat = selectedRadio.value;
        const selectedOption = voterSelect.options[voterSelect.selectedIndex];
        const namaPemilih = selectedOption.getAttribute('data-name') || 'Santri';

        // Tampilkan state loading pada tombol
        submitBtn.disabled = true;
        const originalBtnHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = `
          <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Menyimpan Pilihan...</span>
        `;

        try {
          const response = await fetch('/voting/', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            },
            body: JSON.stringify({
              nis: nis,
              kandidat: kandidat,
              alasan: alasan
            })
          });

          const result = await response.json();

          if (response.ok && result.success) {
            // Tampilkan modal ucapan terima kasih
            if (modalVoterName) modalVoterName.textContent = namaPemilih;
            if (modalCandidateName) modalCandidateName.textContent = kandidat;

            thankYouModal.classList.remove('hidden');
            thankYouModal.classList.add('flex');

            // Mulai countdown redirect
            startRedirectCountdown();
          } else {
            alert('Gagal: ' + (result.message || 'Terjadi kesalahan saat menyimpan suara.'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
          }
        } catch (err) {
          console.error(err);
          // Fallback ke submit formulir biasa jika fetch mengalami kendala
          votingForm.submit();
        }
      });
    });
  </script>

</body>
</html>
