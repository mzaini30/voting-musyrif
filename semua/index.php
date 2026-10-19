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

// 1. Ambil data Santri & Asatidz sebagai referensi lookup
$santriJsonFile = __DIR__ . '/../Data Santri.json';
$allSantri = file_exists($santriJsonFile) ? json_decode(file_get_contents($santriJsonFile), true) : [];
if (!is_array($allSantri)) $allSantri = [];

$asatidzJsonFile = __DIR__ . '/../Data Asatidz.json';
$allAsatidz = file_exists($asatidzJsonFile) ? json_decode(file_get_contents($asatidzJsonFile), true) : [];
if (!is_array($allAsatidz)) $allAsatidz = [];

$santriNisMap = [];
foreach ($allSantri as $s) {
    if (!empty($s['NIS'])) {
        $santriNisMap[(string)$s['NIS']] = $s;
    }
}

$asatidzNisMap = [];
foreach ($allAsatidz as $a) {
    if (!empty($a['NIS'])) {
        $asatidzNisMap[(string)$a['NIS']] = $a;
    }
}

// 2. Daftar Kandidat Musyrif & Musyrifah Resmi
$kandidatMaster = [
    'Ustadz Hanif' => [
        'nama' => 'Ustadz Hanif',
        'kategori' => 'Musyrif',
        'gender' => 'Laki-laki',
        'foto' => '/Foto%20Musyrif/Ustadz%20Hanif.png',
        'deskripsi' => 'Musyrif Halaqoh & Pembina Asrama Putra'
    ],
    'Ustadz Nawwar' => [
        'nama' => 'Ustadz Nawwar',
        'kategori' => 'Musyrif',
        'gender' => 'Laki-laki',
        'foto' => '/Foto%20Musyrif/Ustadz%20Nawwar.png',
        'deskripsi' => 'Musyrif Halaqoh & Pembina Asrama Putra'
    ],
    'Ustadz Zakki' => [
        'nama' => 'Ustadz Zakki',
        'kategori' => 'Musyrif',
        'gender' => 'Laki-laki',
        'foto' => '/Foto%20Musyrif/Ustadz%20Zakki.png',
        'deskripsi' => 'Musyrif Halaqoh & Pembina Asrama Putra'
    ],
    'Ustadzah Marwa' => [
        'nama' => 'Ustadzah Marwa',
        'kategori' => 'Musyrifah',
        'gender' => 'Perempuan',
        'foto' => '/Foto%20Musyrif/Ustadzah%20Marwa.jpg',
        'deskripsi' => 'Musyrifah Halaqoh & Pembina Asrama Putri'
    ],
    'Ustadzah Rahmah' => [
        'nama' => 'Ustadzah Rahmah',
        'kategori' => 'Musyrifah',
        'gender' => 'Perempuan',
        'foto' => '/Foto%20Musyrif/Ustadzah%20Rahmah.jpg',
        'deskripsi' => 'Musyrifah Halaqoh & Pembina Asrama Putri'
    ],
    'Ustadzah Rania' => [
        'nama' => 'Ustadzah Rania',
        'kategori' => 'Musyrifah',
        'gender' => 'Perempuan',
        'foto' => '/Foto%20Musyrif/Ustadzah%20Rania.jpg',
        'deskripsi' => 'Musyrifah Halaqoh & Pembina Asrama Putri'
    ],
];

// 3. Ambil seluruh data voting dari SleekDB
$allVotes = $voteStore->findAll();

// Helper fungsi format tanggal Indonesia
function formatTanggalIndo($datetimeStr) {
    if (empty($datetimeStr)) return '-';
    $ts = strtotime($datetimeStr);
    if (!$ts) return htmlspecialchars($datetimeStr, ENT_QUOTES, 'UTF-8');
    
    $bulanIndo = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $d = date('d', $ts);
    $m = (int)date('m', $ts);
    $y = date('Y', $ts);
    $time = date('H:i', $ts);
    
    return $d . ' ' . ($bulanIndo[$m] ?? date('M', $ts)) . ' ' . $y . ', ' . $time . ' WITA';
}

// 4. Proses dan kelompokkan data per Musyrif
$groupedVotes = [];
foreach ($kandidatMaster as $nama => $info) {
    $groupedVotes[$nama] = [
        'info' => $info,
        'votes' => []
    ];
}

$totalSantriVotes = 0;
$totalAsatidzVotes = 0;
$totalVotesCount = count($allVotes);

foreach ($allVotes as $v) {
    $candName = trim($v['kandidat'] ?? '');
    if (empty($candName)) continue;

    // Normalisasi voter info & tipe pemilih
    $nis = trim((string)($v['nis'] ?? ''));
    $isAsatidz = false;
    if (isset($v['tipe_pemilih']) && strcasecmp($v['tipe_pemilih'], 'Asatidz') === 0) {
        $isAsatidz = true;
    } elseif (str_starts_with($nis, 'AST-') || isset($asatidzNisMap[$nis])) {
        $isAsatidz = true;
    }

    if ($isAsatidz) {
        $totalAsatidzVotes++;
    } else {
        $totalSantriVotes++;
    }

    $namaPemilih = $v['nama_pemilih'] ?? '';
    $namaPanggilan = $v['nama_panggilan'] ?? '';
    if (empty($namaPanggilan)) {
        if ($isAsatidz && isset($asatidzNisMap[$nis]['Nama Panggilan'])) {
            $namaPanggilan = $asatidzNisMap[$nis]['Nama Panggilan'];
        } elseif (!$isAsatidz && isset($santriNisMap[$nis]['Nama Panggilan'])) {
            $namaPanggilan = $santriNisMap[$nis]['Nama Panggilan'];
        }
    }

    $voteItem = [
        'id' => $v['_id'] ?? '',
        'nis' => $nis,
        'nama_pemilih' => $namaPemilih,
        'nama_panggilan' => $namaPanggilan,
        'jenis_kelamin' => $v['jenis_kelamin'] ?? ($isAsatidz ? 'Laki-laki' : 'Santri'),
        'alasan' => trim($v['alasan'] ?? ''),
        'waktu_voting' => $v['waktu_voting'] ?? '',
        'waktu_formatted' => formatTanggalIndo($v['waktu_voting'] ?? ''),
        'is_asatidz' => $isAsatidz,
        'tipe_label' => $isAsatidz ? 'Asatidz / Pembina' : 'Santri'
    ];

    if (!isset($groupedVotes[$candName])) {
        $groupedVotes[$candName] = [
            'info' => [
                'nama' => $candName,
                'kategori' => $v['kategori'] ?? 'Musyrif',
                'gender' => $v['jenis_kelamin'] ?? 'Laki-laki',
                'foto' => '',
                'deskripsi' => 'Kandidat'
            ],
            'votes' => []
        ];
    }

    $groupedVotes[$candName]['votes'][] = $voteItem;
}

// Urutkan grup: Musyrif lebih dulu, lalu Musyrifah, diurutkan suara terbanyak
uasort($groupedVotes, function($a, $b) {
    $katA = strcasecmp($a['info']['kategori'], 'Musyrifah') === 0 ? 1 : 0;
    $katB = strcasecmp($b['info']['kategori'], 'Musyrifah') === 0 ? 1 : 0;
    if ($katA !== $katB) {
        return $katA <=> $katB;
    }
    $countA = count($a['votes']);
    $countB = count($b['votes']);
    if ($countA !== $countB) {
        return $countB <=> $countA;
    }
    return strcasecmp($a['info']['nama'], $b['info']['nama']);
});

// Hitung total suara kategori Musyrif dan Musyrifah
$musyrifVotesCount = 0;
$musyrifahVotesCount = 0;
foreach ($groupedVotes as $nama => $data) {
    $kategori = $data['info']['kategori'] ?? 'Musyrif';
    $count = count($data['votes']);
    if (strcasecmp($kategori, 'Musyrifah') === 0) {
        $musyrifahVotesCount += $count;
    } else {
        $musyrifVotesCount += $count;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Semua Kesan Santri per Musyrif | E-Voting Musyrif</title>

  <!-- Tailwind CSS & Google Fonts -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .quote-bubble {
      position: relative;
    }
    .quote-bubble::before {
      content: '“';
      position: absolute;
      top: -14px;
      left: -2px;
      font-size: 50px;
      font-family: Georgia, serif;
      line-height: 1;
      opacity: 0.12;
      pointer-events: none;
    }
    @media print {
      .no-print {
        display: none !important;
      }
      body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 11pt !important;
      }
      .page-break {
        page-break-before: always;
      }
      .card-print {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #cbd5e1 !important;
      }
    }
  </style>
</head>
<body id="top" class="bg-gradient-to-br from-slate-50 via-teal-50/20 to-emerald-50/40 text-slate-800 min-h-screen flex flex-col antialiased selection:bg-emerald-500 selection:text-white">

  <!-- Top Sticky Navigation Bar -->
  <header class="no-print sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-sm transition duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
      
      <!-- Brand & Link Beranda -->
      <a href="/" class="flex items-center gap-3 group">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center font-black shadow-md shadow-emerald-500/20 group-hover:scale-105 transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
          </svg>
        </div>
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 block leading-tight">E-Voting Musyrif</span>
          <span class="text-base sm:text-lg font-black text-slate-900 group-hover:text-emerald-700 transition">Koleksi Kesan Santri</span>
        </div>
      </a>

      <!-- Quick Nav Links & Actions -->
      <div class="flex items-center gap-2 sm:gap-3">
        <a href="/" class="hidden sm:inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-slate-600 hover:text-emerald-600 px-3 py-2 rounded-xl hover:bg-slate-100 transition">
          <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
          </svg>
          Hasil Voting
        </a>

        <a href="/voting/" class="hidden sm:inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-slate-600 hover:text-emerald-600 px-3 py-2 rounded-xl hover:bg-slate-100 transition">
          <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
          Form Voting
        </a>

        <!-- Tombol Print / Cetak -->
        <button
          onclick="window.print()"
          type="button"
          class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-300 px-3.5 py-2 rounded-xl transition shadow-sm">
          <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
          </svg>
          <span>Cetak</span>
        </button>

        <!-- Tombol Salin Ringkasan -->
        <button
          onclick="copySummaryToClipboard()"
          type="button"
          class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-3.5 py-2 rounded-xl transition shadow-md shadow-emerald-600/20">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          <span class="hidden xs:inline">Salin Semua</span>
        </button>
      </div>

    </div>
  </header>

  <!-- Main Container -->
  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- HERO SECTION -->
    <div class="mb-8 text-center sm:text-left sm:flex sm:items-end sm:justify-between border-b border-slate-200/80 pb-6 gap-6">
      <div class="max-w-2xl">
        <div class="inline-flex items-center gap-2 bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1 rounded-full mb-3">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          Koleksi Kesan & Pesan Santri
        </div>
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-tight">
          Semua Kesan Santri
        </h1>
        <p class="mt-2 text-slate-600 text-sm sm:text-base leading-relaxed">
          Kumpulan seluruh ungkapan, apresiasi, dan catatan santri & asatidz yang dikelompokkan secara terstruktur untuk setiap Musyrif dan Musyrifah.
        </p>
      </div>

      <!-- Overview Stats Mini Cards -->
      <div class="mt-6 sm:mt-0 flex flex-wrap items-center justify-center sm:justify-end gap-3 shrink-0">
        <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 shadow-sm text-center min-w-[105px]">
          <span class="block text-2xl font-black text-emerald-600 leading-none"><?= $totalVotesCount ?></span>
          <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider mt-1 block">Total Kesan</span>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 shadow-sm text-center min-w-[105px]">
          <span class="block text-2xl font-black text-teal-600 leading-none"><?= $musyrifVotesCount ?></span>
          <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider mt-1 block">Musyrif</span>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 shadow-sm text-center min-w-[105px]">
          <span class="block text-2xl font-black text-amber-600 leading-none"><?= $musyrifahVotesCount ?></span>
          <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider mt-1 block">Musyrifah</span>
        </div>
      </div>
    </div>

    <!-- QUICK JUMP CANDIDATE PILLS (STICKY-READY) -->
    <div class="no-print mb-6">
      <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        Lompat Cepat ke Musyrif:
      </div>
      <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-thin">
        <button
          onclick="filterByCandidate('all')"
          id="pill-all"
          class="candidate-pill active shrink-0 inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs sm:text-sm font-bold bg-slate-900 text-white shadow-sm hover:opacity-95 transition">
          <span>Semua</span>
          <span class="bg-white/20 text-white text-[11px] px-1.5 py-0.2 rounded-full"><?= $totalVotesCount ?></span>
        </button>

        <?php foreach ($groupedVotes as $nama => $data): 
          $candCount = count($data['votes']);
          $candSlug = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($nama));
          $candFoto = $data['info']['foto'] ?? '';
          $isMf = strcasecmp($data['info']['kategori'], 'Musyrifah') === 0;
        ?>
        <button
          onclick="filterByCandidate('<?= $candSlug ?>')"
          id="pill-<?= $candSlug ?>"
          class="candidate-pill shrink-0 inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs sm:text-sm font-semibold bg-white border border-slate-200 text-slate-700 hover:border-emerald-400 hover:text-emerald-700 shadow-sm transition">
          <?php if (!empty($candFoto)): ?>
            <img src="<?= $candFoto ?>" alt="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>" class="w-5 h-5 rounded-full object-cover">
          <?php endif; ?>
          <span><?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="text-[11px] font-bold px-1.5 py-0.2 rounded-full <?= $isMf ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
            <?= $candCount ?>
          </span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- FILTER & SEARCH TOOLBAR (NO-PRINT) -->
    <div class="no-print bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-8">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">

        <!-- Search Input -->
        <div class="relative flex-1">
          <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
          </div>
          <input
            type="text"
            id="searchInput"
            placeholder="Cari nama santri, NIS, musyrif, atau kata dalam kesan..."
            class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition"
            oninput="handleSearch()">
          <button
            type="button"
            id="clearSearchBtn"
            onclick="clearSearch()"
            class="hidden absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Filter Buttons (Kategori & Pemilih) -->
        <div class="flex flex-wrap items-center gap-2">

          <!-- Kategori Filter -->
          <div class="inline-flex bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs font-semibold">
            <button
              onclick="setCategoryFilter('all')"
              id="catBtn-all"
              class="cat-filter-btn px-3 py-1.5 rounded-lg bg-white text-slate-900 shadow-sm transition">
              Semua
            </button>
            <button
              onclick="setCategoryFilter('Musyrif')"
              id="catBtn-Musyrif"
              class="cat-filter-btn px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition">
              Musyrif
            </button>
            <button
              onclick="setCategoryFilter('Musyrifah')"
              id="catBtn-Musyrifah"
              class="cat-filter-btn px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition">
              Musyrifah
            </button>
          </div>

          <!-- Tipe Pemilih Filter -->
          <div class="inline-flex bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs font-semibold">
            <button
              onclick="setVoterTypeFilter('all')"
              id="voterBtn-all"
              class="voter-filter-btn px-3 py-1.5 rounded-lg bg-white text-slate-900 shadow-sm transition">
              Semua Pemilih
            </button>
            <button
              onclick="setVoterTypeFilter('santri')"
              id="voterBtn-santri"
              class="voter-filter-btn px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition">
              Santri
            </button>
            <button
              onclick="setVoterTypeFilter('asatidz')"
              id="voterBtn-asatidz"
              class="voter-filter-btn px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 transition">
              Asatidz
            </button>
          </div>

        </div>

      </div>

      <!-- Status Hasil Pencarian -->
      <div id="searchStatsRow" class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
        <span id="searchResultText">Menampilkan <strong><?= $totalVotesCount ?></strong> dari <?= $totalVotesCount ?> seluruh kesan</span>
        <span class="text-slate-400">Gunakan tombol salin untuk menyimpan teks</span>
      </div>
    </div>

    <!-- EMPTY STATE JIKA PENCARIAN KOSONG -->
    <div id="noResultsAlert" class="hidden bg-white border border-slate-200 rounded-3xl p-12 text-center max-w-md mx-auto my-12 shadow-sm">
      <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-400">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <h3 class="text-lg font-bold text-slate-800 mb-1">Tidak Ada Kesan yang Cocok</h3>
      <p class="text-sm text-slate-500 mb-6">Coba periksa kata kunci atau ubah pengaturan filter di atas.</p>
      <button onclick="resetAllFilters()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition shadow">
        Reset Pencarian
      </button>
    </div>

    <!-- SECTIONS GROUPING PER MUSYRIF -->
    <div id="allMusyrifSections" class="space-y-12">

      <?php foreach ($groupedVotes as $nama => $data):
        $info = $data['info'];
        $votes = $data['votes'];
        $kategori = $info['kategori'] ?? 'Musyrif';
        $isMusyrifah = strcasecmp($kategori, 'Musyrifah') === 0;
        $foto = $info['foto'] ?? '';
        $candSlug = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($nama));
        $count = count($votes);
      ?>
      <section
        id="section-<?= $candSlug ?>"
        data-candidate="<?= $candSlug ?>"
        data-candidate-name="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>"
        data-kategori="<?= htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8') ?>"
        class="musyrif-group-section bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden transition duration-300 card-print">

        <!-- HEADER MUSYRIF CARD -->
        <div class="p-6 sm:p-8 bg-gradient-to-r <?= $isMusyrifah ? 'from-amber-500/10 via-rose-500/5 to-transparent border-b border-amber-200/60' : 'from-emerald-500/10 via-teal-500/5 to-transparent border-b border-emerald-200/60' ?>">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">

            <!-- Musyrif Avatar & Info -->
            <div class="flex items-center gap-5">
              <div class="relative shrink-0">
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl overflow-hidden border-4 <?= $isMusyrifah ? 'border-amber-400' : 'border-emerald-500' ?> shadow-lg bg-slate-900">
                  <?php if (!empty($foto)): ?>
                    <img src="<?= $foto ?>" alt="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover">
                  <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center font-black text-2xl text-white bg-slate-800">
                      <?= mb_substr($nama, 0, 1) ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="absolute -bottom-2 -right-2 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider text-white <?= $isMusyrifah ? 'bg-amber-600' : 'bg-emerald-600' ?> shadow">
                  <?= $isMusyrifah ? 'Akhwat' : 'Ikhwan' ?>
                </div>
              </div>

              <div>
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                  <span class="text-xs font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full <?= $isMusyrifah ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' ?>">
                    <?= htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <span class="text-xs text-slate-500 font-medium">
                    <?= htmlspecialchars($info['deskripsi'] ?? 'Pembina Santri', ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>

                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                  <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <div class="mt-2 flex items-center gap-3 text-xs sm:text-sm text-slate-600">
                  <span class="font-bold text-slate-800 candidate-vote-counter">
                    <strong class="text-base text-slate-900 font-extrabold"><?= $count ?></strong> Pesan & Kesan
                  </span>
                  <span>&bull;</span>
                  <span class="text-slate-500">
                    <?= $totalVotesCount > 0 ? round(($count / $totalVotesCount) * 100, 1) : 0 ?>% dari total suara
                  </span>
                </div>
              </div>
            </div>

            <!-- Musyrif Header Action Buttons -->
            <div class="no-print flex items-center gap-2 shrink-0">
              <button
                type="button"
                data-candidate-target="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>"
                onclick="copyCandidateQuotes(this.getAttribute('data-candidate-target'))"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 shadow-sm transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <span>Salin Semua Kesan</span>
              </button>
            </div>

          </div>
        </div>

        <!-- LIST CARDS KESAN SANTRI -->
        <div class="p-6 sm:p-8">

          <?php if (empty($votes)): ?>
            <div class="text-center py-10 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
              <p class="text-slate-500 text-sm">Belum ada kesan santri untuk kandidat ini.</p>
            </div>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 quotes-container">

              <?php foreach ($votes as $idx => $v): 
                $isAsatidz = $v['is_asatidz'];
                $alasanRaw = $v['alasan'];
                $namaPemilih = $v['nama_pemilih'];
                $namaPanggilan = $v['nama_panggilan'];
                $nis = $v['nis'];
                $waktu = $v['waktu_formatted'];
                $searchText = mb_strtolower($namaPemilih . ' ' . $namaPanggilan . ' ' . $nis . ' ' . $alasanRaw . ' ' . $nama, 'UTF-8');
              ?>
              <div
                class="kesan-card flex flex-col justify-between bg-slate-50/70 hover:bg-white border border-slate-200/90 hover:border-emerald-300 hover:shadow-lg rounded-2xl p-5 transition duration-200 relative group card-print"
                data-voter-type="<?= $isAsatidz ? 'asatidz' : 'santri' ?>"
                data-quote="<?= htmlspecialchars($alasanRaw, ENT_QUOTES, 'UTF-8') ?>"
                data-author="<?= htmlspecialchars($namaPemilih, ENT_QUOTES, 'UTF-8') ?>"
                data-cand="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>"
                data-search-text="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Bagian Atas Kartu: Header & Badge -->
                <div class="mb-3">
                  <div class="flex items-center justify-between gap-2 mb-2.5">
                    <!-- Badge Pemilih -->
                    <?php if ($isAsatidz): ?>
                      <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200">
                        <svg class="w-3 h-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                        </svg>
                        Asatidz / Pembina
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Santri
                      </span>
                    <?php endif; ?>

                    <span class="text-[11px] text-slate-400 font-mono font-bold">
                      #<?= $idx + 1 ?>
                    </span>
                  </div>

                  <!-- Teks Pesan & Kesan Santri -->
                  <div class="quote-bubble relative text-slate-800 text-sm leading-relaxed pl-1 pt-1 font-normal break-words">
                    <p class="whitespace-pre-line"><?= htmlspecialchars($alasanRaw, ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>

                <!-- Bagian Bawah Kartu: Info Penulis / Santri -->
                <div class="mt-4 pt-3 border-t border-slate-200/80 flex items-end justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    <div class="text-xs font-bold text-slate-900 truncate flex items-center gap-1.5">
                      <span><?= htmlspecialchars($namaPemilih, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5 flex-wrap">
                      <?php if (!empty($namaPanggilan) && strcasecmp($namaPanggilan, $namaPemilih) !== 0): ?>
                        <span class="text-emerald-700 font-medium">"<?= htmlspecialchars($namaPanggilan, ENT_QUOTES, 'UTF-8') ?>"</span>
                        <span>&bull;</span>
                      <?php endif; ?>
                      <span class="font-mono text-slate-400"><?= htmlspecialchars($nis, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="text-[10px] text-slate-400 mt-1">
                      <?= htmlspecialchars($waktu, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  </div>

                  <!-- Tombol Salin Cepat untuk Kartu ini -->
                  <button
                    type="button"
                    onclick="copyCardQuote(this)"
                    title="Salin kesan ini"
                    class="no-print p-1.5 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition shrink-0 opacity-70 group-hover:opacity-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    </svg>
                  </button>
                </div>

              </div>
              <?php endforeach; ?>

            </div>
          <?php endif; ?>

        </div>

      </section>
      <?php endforeach; ?>

    </div>

  </main>

  <!-- FOOTER -->
  <footer class="no-print mt-16 bg-white border-t border-slate-200 py-8 text-center text-xs text-slate-500">
    <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div>
        &copy; 2026 E-Voting Musyrif &bull; Arsip Lengkap Kesan Santri & Asatidz
      </div>
      <div class="flex items-center gap-4">
        <a href="/" class="hover:text-emerald-600 transition">Halaman Hasil</a>
        <span>&bull;</span>
        <a href="/voting/" class="hover:text-emerald-600 transition">Halaman Voting</a>
        <span>&bull;</span>
        <a href="#top" class="hover:text-emerald-600 transition">Kembali ke Atas &uarr;</a>
      </div>
    </div>
  </footer>

  <!-- FLOATING BACK TO TOP BUTTON -->
  <button
    id="btnBackToTop"
    onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
    type="button"
    class="no-print fixed bottom-6 right-6 p-3 rounded-2xl bg-slate-900/90 text-white shadow-xl hover:bg-emerald-600 transition transform hover:-translate-y-1 opacity-0 pointer-events-none duration-300 z-50">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
    </svg>
  </button>

  <!-- TOAST NOTIFICATION -->
  <div
    id="toastNotification"
    class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3 text-xs sm:text-sm font-semibold transition-all duration-300 opacity-0 translate-y-4 pointer-events-none z-50">
    <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
    </svg>
    <span id="toastMessage">Teks berhasil disalin ke clipboard!</span>
  </div>

  <!-- SCRIPT FILTER, SEARCH, COPY & INTERACTIVITY -->
  <script>
    // Data Grouped dari PHP untuk kebutuhan copy & export
    const candidateData = <?= json_encode($groupedVotes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    
    // State Filter Aktif
    let currentCandidateFilter = 'all';
    let currentCategoryFilter = 'all';
    let currentVoterTypeFilter = 'all';
    let currentSearchTerm = '';

    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const noResultsAlert = document.getElementById('noResultsAlert');
    const searchResultText = document.getElementById('searchResultText');
    const btnBackToTop = document.getElementById('btnBackToTop');

    // Tampilkan / Sembunyikan Tombol Back to Top saat scrolling
    window.addEventListener('scroll', () => {
      if (window.scrollY > 400) {
        btnBackToTop.classList.remove('opacity-0', 'pointer-events-none');
        btnBackToTop.classList.add('opacity-100', 'pointer-events-auto');
      } else {
        btnBackToTop.classList.add('opacity-0', 'pointer-events-none');
        btnBackToTop.classList.remove('opacity-100', 'pointer-events-auto');
      }
    });

    // Toast Function
    let toastTimeout;
    function showToast(message) {
      const toast = document.getElementById('toastNotification');
      const toastMessage = document.getElementById('toastMessage');
      toastMessage.textContent = message;
      toast.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');
      toast.classList.add('opacity-100', 'translate-y-0');

      clearTimeout(toastTimeout);
      toastTimeout = setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
        toast.classList.remove('opacity-100', 'translate-y-0');
      }, 3000);
    }

    // Filter Cepat Berdasarkan Kandidat (Pills)
    function filterByCandidate(slug) {
      currentCandidateFilter = slug;

      // Update UI Tombol Pill
      document.querySelectorAll('.candidate-pill').forEach(btn => {
        btn.classList.remove('active', 'bg-slate-900', 'text-white');
        btn.classList.add('bg-white', 'text-slate-700');
      });

      const activeBtn = document.getElementById('pill-' + slug);
      if (activeBtn) {
        activeBtn.classList.add('active', 'bg-slate-900', 'text-white');
        activeBtn.classList.remove('bg-white', 'text-slate-700');
      }

      // Jika klik kandidat spesifik, reset filter kategori ke 'all' agar tidak konflik
      if (slug !== 'all') {
        currentCategoryFilter = 'all';
        document.querySelectorAll('.cat-filter-btn').forEach(btn => {
          btn.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
          btn.classList.add('text-slate-600');
        });
        const catAll = document.getElementById('catBtn-all');
        if (catAll) {
          catAll.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
          catAll.classList.remove('text-slate-600');
        }
      }

      applyAllFilters();

      // Scroll halus ke seksi kandidat dengan offset sticky header
      if (slug !== 'all') {
        setTimeout(() => {
          const sec = document.getElementById('section-' + slug);
          if (sec && !sec.classList.contains('hidden')) {
            const yOffset = -85;
            const y = sec.getBoundingClientRect().top + window.pageYOffset + yOffset;
            window.scrollTo({ top: y, behavior: 'smooth' });
          }
        }, 50);
      }
    }

    // Filter Kategori (Musyrif / Musyrifah)
    function setCategoryFilter(cat) {
      currentCategoryFilter = cat;
      currentCandidateFilter = 'all';

      // Reset pill kandidat ke 'all'
      document.querySelectorAll('.candidate-pill').forEach(btn => {
        btn.classList.remove('active', 'bg-slate-900', 'text-white');
        btn.classList.add('bg-white', 'text-slate-700');
      });
      const pillAll = document.getElementById('pill-all');
      if (pillAll) {
        pillAll.classList.add('active', 'bg-slate-900', 'text-white');
        pillAll.classList.remove('bg-white', 'text-slate-700');
      }

      document.querySelectorAll('.cat-filter-btn').forEach(btn => {
        btn.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
        btn.classList.add('text-slate-600');
      });
      const active = document.getElementById('catBtn-' + cat);
      if (active) {
        active.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
        active.classList.remove('text-slate-600');
      }
      applyAllFilters();
    }

    // Filter Tipe Pemilih (Semua / Santri / Asatidz)
    function setVoterTypeFilter(type) {
      currentVoterTypeFilter = type;
      document.querySelectorAll('.voter-filter-btn').forEach(btn => {
        btn.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
        btn.classList.add('text-slate-600');
      });
      const active = document.getElementById('voterBtn-' + type);
      if (active) {
        active.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
        active.classList.remove('text-slate-600');
      }
      applyAllFilters();
    }

    // Search Input Handler
    function handleSearch() {
      currentSearchTerm = searchInput.value.trim().toLowerCase();
      if (currentSearchTerm.length > 0) {
        clearSearchBtn.classList.remove('hidden');
      } else {
        clearSearchBtn.classList.add('hidden');
      }
      applyAllFilters();
    }

    function clearSearch() {
      searchInput.value = '';
      currentSearchTerm = '';
      clearSearchBtn.classList.add('hidden');
      applyAllFilters();
    }

    function resetAllFilters() {
      clearSearch();
      setCategoryFilter('all');
      setVoterTypeFilter('all');
      filterByCandidate('all');
    }

    // Logika Penggabungan Seluruh Filter & Pencarian
    function applyAllFilters() {
      const sections = document.querySelectorAll('.musyrif-group-section');
      let totalVisibleCards = 0;
      let totalSectionsVisible = 0;

      sections.forEach(sec => {
        const candidateSlug = sec.getAttribute('data-candidate');
        const kategori = sec.getAttribute('data-kategori');

        // 1. Cek kecocokan Kandidat
        const matchCandidate = (currentCandidateFilter === 'all' || currentCandidateFilter === candidateSlug);

        // 2. Cek kecocokan Kategori
        const matchCategory = (currentCategoryFilter === 'all' || currentCategoryFilter.toLowerCase() === kategori.toLowerCase());

        if (!matchCandidate || !matchCategory) {
          sec.classList.add('hidden');
          return;
        }

        // 3. Filter Kartu di dalam Seksi ini
        const cards = sec.querySelectorAll('.kesan-card');
        let visibleCardsInSection = 0;

        cards.forEach(card => {
          const voterType = card.getAttribute('data-voter-type'); // 'santri' / 'asatidz'
          const searchText = card.getAttribute('data-search-text') || '';

          // Filter Tipe Pemilih
          const matchVoterType = (currentVoterTypeFilter === 'all' || currentVoterTypeFilter === voterType);

          // Filter Kata Kunci Pencarian
          const matchSearch = (currentSearchTerm === '' || searchText.includes(currentSearchTerm));

          if (matchVoterType && matchSearch) {
            card.classList.remove('hidden');
            visibleCardsInSection++;
            totalVisibleCards++;
          } else {
            card.classList.add('hidden');
          }
        });

        // Jika tidak ada kartu yang cocok dalam seksi ini saat pencarian aktif, sembunyikan seksi
        if (visibleCardsInSection === 0 && (currentSearchTerm !== '' || currentVoterTypeFilter !== 'all')) {
          sec.classList.add('hidden');
        } else {
          sec.classList.remove('hidden');
          totalSectionsVisible++;
        }
      });

      // Update Label Counter Hasil
      if (searchResultText) {
        searchResultText.innerHTML = `Menampilkan <strong>${totalVisibleCards}</strong> kesan santri`;
      }

      // Tampilkan Empty State jika 0 hasil
      if (totalVisibleCards === 0) {
        noResultsAlert.classList.remove('hidden');
      } else {
        noResultsAlert.classList.add('hidden');
      }
    }

    // Salin Satu Kartu Kesan
    function copyCardQuote(btn) {
      const card = btn.closest('.kesan-card');
      if (!card) return;
      const quote = card.getAttribute('data-quote') || '';
      const author = card.getAttribute('data-author') || '';
      const cand = card.getAttribute('data-cand') || '';
      const textToCopy = `"${quote}"\n— ${author} (untuk ${cand})`;
      
      navigator.clipboard.writeText(textToCopy).then(() => {
        showToast('Kesan dari ' + author + ' berhasil disalin!');
      }).catch(err => {
        console.error('Gagal menyalin:', err);
      });
    }

    // Salin Semua Kesan untuk 1 Musyrif Tertentu
    function copyCandidateQuotes(namaMusyrif) {
      const data = candidateData[namaMusyrif];
      if (!data || !data.votes || data.votes.length === 0) {
        showToast('Tidak ada kesan untuk ' + namaMusyrif);
        return;
      }

      let text = `=== SEMUA KESAN SANTRI UNTUK ${namaMusyrif.toUpperCase()} (${data.votes.length} KESAN) ===\n\n`;
      data.votes.forEach((v, i) => {
        text += `${i + 1}. "${v.alasan}"\n   Oleh: ${v.nama_pemilih} (${v.tipe_label}, NIS: ${v.nis}) - ${v.waktu_formatted}\n\n`;
      });

      navigator.clipboard.writeText(text).then(() => {
        showToast('Seluruh kesan untuk ' + namaMusyrif + ' berhasil disalin!');
      }).catch(err => {
        console.error('Gagal menyalin:', err);
      });
    }

    // Salin Ringkasan Seluruh Kesan Santri Semua Musyrif
    function copySummaryToClipboard() {
      let fullText = `=====================================================\n`;
      fullText += `KOLEKSI SELURUH KESAN SANTRI PER MUSYRIF & MUSYRIFAH\n`;
      fullText += `=====================================================\n\n`;

      for (const [nama, data] of Object.entries(candidateData)) {
        fullText += `-----------------------------------------------------\n`;
        fullText += `KANDIDAT: ${nama.toUpperCase()} (${data.info.kategori})\n`;
        fullText += `Total Kesan: ${data.votes.length}\n`;
        fullText += `-----------------------------------------------------\n`;

        if (data.votes.length === 0) {
          fullText += `(Belum ada kesan)\n\n`;
          continue;
        }

        data.votes.forEach((v, i) => {
          fullText += `${i + 1}. "${v.alasan}"\n   — ${v.nama_pemilih} (${v.tipe_label}) [${v.waktu_formatted}]\n\n`;
        });
      }

      navigator.clipboard.writeText(fullText).then(() => {
        showToast('Seluruh kumpulan kesan santri berhasil disalin!');
      }).catch(err => {
        console.error('Gagal menyalin:', err);
      });
    }
  </script>

</body>
</html>
