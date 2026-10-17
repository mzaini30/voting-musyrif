<?php
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/vendor/autoload.php';

use SleekDB\Store;

$databaseDir = __DIR__ . '/database';
if (!is_dir($databaseDir)) {
    mkdir($databaseDir, 0777, true);
}

$voteStore = new Store('votes', $databaseDir, [
    'auto_cache' => false
]);

// Cari file audio confetti secara dinamis
$audioFile = null;
$files = scandir(__DIR__);
foreach ($files as $f) {
    if (str_ends_with($f, '.mp3') && str_contains($f, 'Confetti')) {
        $audioFile = $f;
        break;
    }
}
$audioUrl = $audioFile ? ('/' . rawurlencode($audioFile)) : '';

// Daftar Calon Musyrif
$musyrifList = [
    [
        'nama' => 'Ustadz Hanif',
        'foto' => '/Foto%20Musyrif/Ustadz%20Hanif.jpg',
        'kategori' => 'Musyrif',
        'suara' => 0
    ],
    [
        'nama' => 'Ustadz Nawwar',
        'foto' => '/Foto%20Musyrif/Ustadz%20Nawwar.jpg',
        'kategori' => 'Musyrif',
        'suara' => 0
    ],
    [
        'nama' => 'Ustadz Zakki',
        'foto' => '/Foto%20Musyrif/Ustadz%20Zakki.jpg',
        'kategori' => 'Musyrif',
        'suara' => 0
    ],
];

// Daftar Calon Musyrifah
$musyrifahList = [
    [
        'nama' => 'Ustadzah Marwa',
        'foto' => '/Foto%20Musyrif/Ustadzah%20Marwa.jpg',
        'kategori' => 'Musyrifah',
        'suara' => 0
    ],
    [
        'nama' => 'Ustadzah Rahmah',
        'foto' => '/Foto%20Musyrif/Ustadzah%20Rahmah.jpg',
        'kategori' => 'Musyrifah',
        'suara' => 0
    ],
    [
        'nama' => 'Ustadzah Rania',
        'foto' => '/Foto%20Musyrif/Ustadzah%20Rania.jpg',
        'kategori' => 'Musyrifah',
        'suara' => 0
    ],
];

// Ambil hasil voting dari SleekDB
$allVotes = $voteStore->findAll();

// Hitung suara per kandidat
foreach ($allVotes as $v) {
    $cand = $v['kandidat'] ?? '';
    foreach ($musyrifList as &$m) {
        if ($m['nama'] === $cand) {
            $m['suara']++;
        }
    }
    unset($m);

    foreach ($musyrifahList as &$mf) {
        if ($mf['nama'] === $cand) {
            $mf['suara']++;
        }
    }
    unset($mf);
}

// Tentukan Musyrif dengan suara terbanyak
$winnerMusyrif = $musyrifList[0];
foreach ($musyrifList as $m) {
    if ($m['suara'] > $winnerMusyrif['suara']) {
        $winnerMusyrif = $m;
    }
}

// Tentukan Musyrifah dengan suara terbanyak
$winnerMusyrifah = $musyrifahList[0];
foreach ($musyrifahList as $mf) {
    if ($mf['suara'] > $winnerMusyrifah['suara']) {
        $winnerMusyrifah = $mf;
    }
}

$totalVotes = count($allVotes);

// Kumpulkan catatan/alasan dari santri untuk Musyrif & Musyrifah terpilih
$rawSantriQuotes = [];
foreach ($allVotes as $v) {
    $kandidat = $v['kandidat'] ?? '';
    $alasan = trim($v['alasan'] ?? '');
    if (!empty($alasan)) {
        if ($kandidat === $winnerMusyrif['nama'] || $kandidat === $winnerMusyrifah['nama']) {
            $rawSantriQuotes[] = mb_strtoupper($alasan, 'UTF-8');
        }
    }
}

// Kata-kata inspiratif & catatan santri sesuai contoh latar dan budaya santri
$defaultQuotes = [
    'KEREN...',
    'AKU SELALU SENANG KALAU BELAJAR SAMA USTADZAH...',
    'AKU SELALU SENANG KALAU BELAJAR SAMA USTADZ...',
    'DIA SELALU BAIK HATI',
    'KANGEN DAURAH INI LAGI... KIRA-KIRA KAPAN LAGI YA BAKALAN ADA',
    'ORANGNYA SABAR DAN TELATEN BANGET',
    'SELALU MEMBIMBING DENGAN LEMBUT',
    'KEREN BANGET CARA MENGAJARNYA!',
    'PEMBIMBING TAHFIDZ TERBAIK KAMI',
    'SANGAT DISIPLIN TAPI PENYAYANG',
    'SELALU MENDENGARKAN KELUH KESAH SANTRI',
    'TELADAN DAN PANUTAN TERBAIK KAMI',
    'RAMAH DAN SELALU TERSENYUM',
    'PENGAJARANNYA SANGAT MUDAH DIPAHAMI',
    'ALHAMDULILLAH DAPAT PEMBIMBING TERBAIK',
    'SEMOGA ALLAH SELALU MEMBERKAHI BELIAU',
    'SUARANYA SANGAT MERDU SAAT MEMIMPIN SHALAT',
    'SANGAT PERHATIAN DENGAN HAFALAN KAMI',
    'USTADZ TERFAVORIT SEPANJANG MASA!',
    'USTADZAH PALING SABAR DAN TELATEN!'
];

// Gabungkan catatan asli santri dengan daftar variasi
$allSantriQuotes = array_values(array_unique(array_merge($rawSantriQuotes, $defaultQuotes)));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengumuman Hasil Voting Musyrif & Musyrifah</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700;800;900&family=Barlow+Condensed:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      transition: background 1.5s ease;
    }
    .font-poster {
      font-family: 'Oswald', 'Barlow Condensed', 'Impact', sans-serif;
      letter-spacing: -0.02em;
      line-height: 0.92;
    }
    @keyframes pulseScale {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.08); }
    }
    @keyframes countPop {
      0% { transform: scale(0.4); opacity: 0; }
      60% { transform: scale(1.15); opacity: 1; }
      100% { transform: scale(1); opacity: 1; }
    }
    .animate-count {
      animation: countPop 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    .slot-shuffling {
      animation: pulseScale 0.25s infinite ease-in-out;
    }
    .winner-glow {
      box-shadow: 0 0 50px rgba(16, 185, 129, 0.5), 0 0 25px rgba(251, 191, 36, 0.5);
    }

    /* Style untuk Slot Catatan Latar Belakang (Contoh Latar.jpg) */
    .bg-quote-slot {
      position: absolute;
      pointer-events: none;
      user-select: none;
      opacity: 0;
      color: #ffffff;
      font-family: 'Oswald', 'Barlow Condensed', 'Impact', sans-serif;
      text-transform: uppercase;
      line-height: 0.92;
      letter-spacing: -0.02em;
      transition: opacity 1.2s ease-in-out;
      text-shadow: 0 0 20px rgba(0, 0, 0, 0.9);
      will-change: opacity;
    }
    .bg-quote-slot.active {
      opacity: 0.82;
    }
    .bg-quote-slot.rotate-90 {
      transform: rotate(90deg);
      transform-origin: center center;
      white-space: nowrap;
    }
    .bg-quote-slot.rotate-0 {
      transform: rotate(0deg);
    }
  </style>
</head>
<body id="pageBody" class="bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 text-white min-h-screen flex flex-col justify-between overflow-x-hidden selection:bg-emerald-500 selection:text-white relative">

  <!-- Audio Element untuk Confetti -->
  <audio id="confettiAudio" preload="auto">
    <source src="<?= $audioUrl ?>" type="audio/mpeg">
  </audio>

  <!-- LAYER LATAR BELAKANG: CATATAN SANTRI (FADE IN / FADE OUT) -->
  <div id="backgroundQuotesLayer" class="fixed inset-0 z-0 pointer-events-none overflow-hidden hidden"></div>

  <!-- Navbar / Header Atas -->
  <header class="w-full max-w-7xl mx-auto px-6 py-4 flex items-center justify-between z-20 relative">
    <div class="flex items-center gap-2">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center font-black text-slate-950 shadow-lg shadow-emerald-500/20">
        V
      </div>
      <span class="font-bold text-sm sm:text-base tracking-wide text-emerald-400">Voting Musyrif & Musyrifah</span>
    </div>
    <div class="flex items-center gap-3">
      <a href="/voting/" class="text-xs sm:text-sm font-semibold bg-slate-800/80 hover:bg-slate-700 text-slate-200 hover:text-white px-4 py-2 rounded-xl border border-slate-700 transition flex items-center gap-1.5 shadow-sm">
        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
        </svg>
        <span>Ke Halaman Voting</span>
      </a>
    </div>
  </header>

  <!-- Konten Utama -->
  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 flex flex-col items-center justify-center relative z-10">

    <!-- 1. TAMPILAN AWAL: TOMBOL LIHAT HASIL -->
    <div id="initialScreen" class="text-center max-w-2xl mx-auto py-12 px-6 flex flex-col items-center animate-fade-in">
      <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs sm:text-sm font-semibold px-4 py-1.5 rounded-full mb-6">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
        Hasil Pemilihan Siap Diumumkan
      </div>

      <h1 class="text-4xl sm:text-6xl font-black text-transparent bg-clip-text bg-gradient-to-r from-white via-slate-100 to-emerald-300 tracking-tight leading-tight mb-4">
        Pengumuman Hasil Voting
      </h1>

      <p class="text-slate-300 text-base sm:text-lg mb-8 leading-relaxed max-w-lg">
        Total <strong class="text-emerald-400"><?= $totalVotes ?> suara</strong> telah tercatat di sistem. Siapakah Musyrif & Musyrifah yang mendapatkan amanah terbanyak?
      </p>

      <button
        id="btnLihatHasil"
        type="button"
        class="group relative inline-flex items-center justify-center px-8 py-5 text-lg sm:text-xl font-extrabold text-slate-950 bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-400 hover:from-emerald-300 hover:to-teal-200 rounded-2xl shadow-2xl shadow-emerald-500/40 hover:shadow-emerald-500/60 transform hover:-translate-y-1 active:translate-y-0 transition duration-200 cursor-pointer">
        <span class="mr-3 text-2xl group-hover:rotate-12 transition transform">🎉</span>
        <span>Lihat Hasil</span>
        <svg class="w-6 h-6 ml-3 transform group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
        </svg>
      </button>
    </div>

    <!-- 2. ARENA PENGUMUMAN (KIRI: MUSYRIF, TENGAH: COUNTDOWN, KANAN: MUSYRIFAH) -->
    <div id="presentationStage" class="hidden w-full flex-col items-center">

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center w-full min-h-[460px]">

        <!-- KOLOM KIRI: MUSYRIF -->
        <div class="lg:col-span-4 flex flex-col items-center">
          <div class="w-full max-w-sm bg-slate-900/85 backdrop-blur-md border-2 border-emerald-500/30 rounded-3xl p-6 flex flex-col items-center text-center shadow-2xl relative transition duration-300" id="cardMusyrif">

            <!-- Badge Kategori -->
            <div id="badgeMusyrif" class="px-4 py-1 rounded-full text-xs font-bold tracking-wider uppercase mb-4 bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
              Calon Musyrif
            </div>

            <!-- Frame Foto -->
            <div class="w-48 h-48 sm:w-56 sm:h-56 rounded-2xl overflow-hidden border-4 border-slate-700 bg-slate-900 shadow-xl mb-4 relative" id="photoFrameMusyrif">
              <img
                id="imgMusyrif"
                src="<?= $musyrifList[0]['foto'] ?>"
                alt="Foto Musyrif"
                class="w-full h-full object-cover transition duration-150">
            </div>

            <!-- Nama Kandidat -->
            <h3 id="nameMusyrif" class="text-xl sm:text-2xl font-black text-white tracking-tight">
              <?= htmlspecialchars($musyrifList[0]['nama']) ?>
            </h3>

            <!-- Informasi Perolehan Suara (Muncul saat Pengumuman) -->
            <div id="votesMusyrif" class="hidden mt-2 text-sm font-semibold text-emerald-300 bg-emerald-950/70 px-4 py-1.5 rounded-xl border border-emerald-500/40">
              Perolehan: <strong class="text-white font-bold" id="voteCountMusyrif">0</strong> Suara
            </div>
          </div>
        </div>

        <!-- KOLOM TENGAH: TULISAN BESAR & HITUNG MUNDUR -->
        <div class="lg:col-span-4 flex flex-col items-center justify-center py-8 text-center px-2">

          <!-- Wadah Teks Pengantar ("Hitung bersama-sama ya....") -->
          <div id="introText" class="hidden animate-count">
            <h2 class="text-3xl sm:text-5xl lg:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-200 via-yellow-300 to-amber-100 tracking-tight leading-snug drop-shadow-lg font-poster">
              Hitung bersama-sama ya....
            </h2>
            <p class="text-slate-400 text-sm mt-3 font-medium">Bersiap dalam hitungan mundur!</p>
          </div>

          <!-- Wadah Angka Hitung Mundur (10 sampai 1) -->
          <div id="countdownBox" class="hidden flex-col items-center justify-center">
            <div
              id="countdownNumber"
              class="text-7xl sm:text-9xl lg:text-[10rem] font-black text-transparent bg-clip-text bg-gradient-to-br from-yellow-300 via-amber-400 to-orange-500 drop-shadow-[0_10px_35px_rgba(245,158,11,0.6)] leading-none select-none font-poster">
              10
            </div>
          </div>

          <!-- Wadah Ucapan Selamat (Muncul saat Selesai) -->
          <div id="winnerBanner" class="hidden flex-col items-center animate-count bg-slate-900/80 backdrop-blur-md p-6 rounded-3xl border border-amber-400/40 shadow-2xl">
            <span class="text-5xl mb-2">🎉 🏆 🎉</span>
            <div class="text-xs sm:text-sm font-bold tracking-widest text-amber-300 uppercase mb-1">
              Alhamdulillah!
            </div>
            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight text-center leading-tight">
              SELAMAT KEPADA<br>
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-teal-200 to-amber-300">
                MUSYRIF & MUSYRIFAH
              </span><br>
              TERPILIH!
            </h2>
            <div class="flex items-center gap-3 mt-6">
              <button
                id="btnReplay"
                type="button"
                class="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs sm:text-sm font-bold px-4 py-2.5 rounded-xl border border-slate-600 transition flex items-center gap-2 cursor-pointer shadow">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Ulangi Hitung Mundur</span>
              </button>
            </div>
          </div>

        </div>

        <!-- KOLOM KANAN: MUSYRIFAH -->
        <div class="lg:col-span-4 flex flex-col items-center">
          <div class="w-full max-w-sm bg-slate-900/85 backdrop-blur-md border-2 border-teal-500/30 rounded-3xl p-6 flex flex-col items-center text-center shadow-2xl relative transition duration-300" id="cardMusyrifah">

            <!-- Badge Kategori -->
            <div id="badgeMusyrifah" class="px-4 py-1 rounded-full text-xs font-bold tracking-wider uppercase mb-4 bg-teal-500/20 text-teal-300 border border-teal-500/40">
              Calon Musyrifah
            </div>

            <!-- Frame Foto -->
            <div class="w-48 h-48 sm:w-56 sm:h-56 rounded-2xl overflow-hidden border-4 border-slate-700 bg-slate-900 shadow-xl mb-4 relative" id="photoFrameMusyrifah">
              <img
                id="imgMusyrifah"
                src="<?= $musyrifahList[0]['foto'] ?>"
                alt="Foto Musyrifah"
                class="w-full h-full object-cover transition duration-150">
            </div>

            <!-- Nama Kandidat -->
            <h3 id="nameMusyrifah" class="text-xl sm:text-2xl font-black text-white tracking-tight">
              <?= htmlspecialchars($musyrifahList[0]['nama']) ?>
            </h3>

            <!-- Informasi Perolehan Suara (Muncul saat Pengumuman) -->
            <div id="votesMusyrifah" class="hidden mt-2 text-sm font-semibold text-teal-300 bg-teal-950/70 px-4 py-1.5 rounded-xl border border-teal-500/40">
              Perolehan: <strong class="text-white font-bold" id="voteCountMusyrifah">0</strong> Suara
            </div>
          </div>
        </div>

      </div>

    </div>

  </main>

  <!-- Footer Bawah -->
  <footer class="w-full max-w-7xl mx-auto px-6 py-4 text-center text-xs text-slate-500 z-20 relative">
    &copy; 2026 E-Voting Santri &bull; Pemilihan Musyrif & Musyrifah
  </footer>

  <!-- Script Presentasi, Confetti & Animasi Catatan Santri di Latar Belakang -->
  <script>
    // Data Kandidat & Catatan Santri dari PHP
    const musyrifList = <?= json_encode($musyrifList, JSON_UNESCAPED_SLASHES) ?>;
    const musyrifahList = <?= json_encode($musyrifahList, JSON_UNESCAPED_SLASHES) ?>;
    const winnerMusyrif = <?= json_encode($winnerMusyrif, JSON_UNESCAPED_SLASHES) ?>;
    const winnerMusyrifah = <?= json_encode($winnerMusyrifah, JSON_UNESCAPED_SLASHES) ?>;
    const santriQuotesPool = <?= json_encode($allSantriQuotes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    // DOM Elements
    const pageBody = document.getElementById('pageBody');
    const initialScreen = document.getElementById('initialScreen');
    const presentationStage = document.getElementById('presentationStage');
    const btnLihatHasil = document.getElementById('btnLihatHasil');
    const btnReplay = document.getElementById('btnReplay');

    const cardMusyrif = document.getElementById('cardMusyrif');
    const photoFrameMusyrif = document.getElementById('photoFrameMusyrif');
    const imgMusyrif = document.getElementById('imgMusyrif');
    const nameMusyrif = document.getElementById('nameMusyrif');
    const badgeMusyrif = document.getElementById('badgeMusyrif');
    const votesMusyrif = document.getElementById('votesMusyrif');
    const voteCountMusyrif = document.getElementById('voteCountMusyrif');

    const cardMusyrifah = document.getElementById('cardMusyrifah');
    const photoFrameMusyrifah = document.getElementById('photoFrameMusyrifah');
    const imgMusyrifah = document.getElementById('imgMusyrifah');
    const nameMusyrifah = document.getElementById('nameMusyrifah');
    const badgeMusyrifah = document.getElementById('badgeMusyrifah');
    const votesMusyrifah = document.getElementById('votesMusyrifah');
    const voteCountMusyrifah = document.getElementById('voteCountMusyrifah');

    const introText = document.getElementById('introText');
    const countdownBox = document.getElementById('countdownBox');
    const countdownNumber = document.getElementById('countdownNumber');
    const winnerBanner = document.getElementById('winnerBanner');
    const confettiAudio = document.getElementById('confettiAudio');

    const backgroundQuotesLayer = document.getElementById('backgroundQuotesLayer');

    let musyrifShuffleInterval = null;
    let musyrifahShuffleInterval = null;
    let backgroundSlotsTimeouts = [];
    let backgroundSlotsIntervals = [];

    // Definisi Slot Tata Letak Catatan Latar Belakang (Seperti Contoh Latar.jpg)
    // Sejak awal sudah di-set rotasi 0 atau 90 derajat, bold atau tidak bold, uppercase semua
    const backgroundSlotConfigs = [
      // Kiri Atas (Horizontal besar, bold)
      { id: 's1', top: '3%', left: '3%', fontSize: 'clamp(1.8rem, 3.2vw, 3.6rem)', bold: true, rotate: 0, maxWidth: '42%' },
      // Kiri Tengah (Vertikal, bold)
      { id: 's2', top: '38%', left: '-2%', fontSize: 'clamp(1.6rem, 2.6vw, 3rem)', bold: true, rotate: 90, width: '380px' },
      // Kiri Tengah Raksasa (KEREN...)
      { id: 's3', top: '22%', left: '16%', fontSize: 'clamp(3rem, 5.5vw, 6.5rem)', bold: true, rotate: 0, maxWidth: '28%' },
      // Kiri Bawah Teks Horizontal Panjang (tidak bold)
      { id: 's4', top: '50%', left: '6%', fontSize: 'clamp(1.2rem, 1.8vw, 1.9rem)', bold: false, rotate: 0, maxWidth: '42%' },
      // Kiri Bawah Raksasa (KEREN...)
      { id: 's5', top: '60%', left: '14%', fontSize: 'clamp(3.2rem, 6vw, 7rem)', bold: true, rotate: 0, maxWidth: '30%' },
      // Kiri Dasar (Horizontal, bold)
      { id: 's6', bottom: '3%', left: '3%', fontSize: 'clamp(1.2rem, 1.9vw, 2.2rem)', bold: true, rotate: 0, maxWidth: '46%' },

      // Tengah Atas (Horizontal, tidak bold)
      { id: 's7', top: '2%', left: '38%', fontSize: 'clamp(1.2rem, 2vw, 2.4rem)', bold: false, rotate: 0, maxWidth: '26%' },
      // Tengah Bawah (Horizontal, bold)
      { id: 's8', bottom: '2%', left: '36%', fontSize: 'clamp(1.3rem, 2.1vw, 2.3rem)', bold: true, rotate: 0, maxWidth: '28%' },

      // Kanan Atas (Horizontal, tidak bold)
      { id: 's9', top: '3%', right: '3%', fontSize: 'clamp(1.5rem, 2.6vw, 2.8rem)', bold: false, rotate: 0, maxWidth: '36%' },
      // Kanan Kolom Vertikal 1 (Vertikal, bold)
      { id: 's10', top: '26%', right: '32%', fontSize: 'clamp(1.4rem, 2.2vw, 2.5rem)', bold: true, rotate: 90, width: '420px' },
      // Kanan Kolom Vertikal 2 (Vertikal, tidak bold)
      { id: 's11', top: '32%', right: '22%', fontSize: 'clamp(1.5rem, 2.4vw, 2.6rem)', bold: false, rotate: 90, width: '380px' },
      // Kanan Kolom Vertikal 3 (Vertikal, bold besar)
      { id: 's12', top: '24%', right: '1%', fontSize: 'clamp(2rem, 3.8vw, 4.2rem)', bold: true, rotate: 90, width: '480px' },
      // Kanan Tengah Raksasa (KEREN...)
      { id: 's13', top: '48%', right: '18%', fontSize: 'clamp(2.4rem, 4.5vw, 5rem)', bold: true, rotate: 90, width: '360px' },
      // Kanan Bawah (Horizontal, bold)
      { id: 's14', bottom: '12%', right: '12%', fontSize: 'clamp(1.8rem, 3vw, 3.4rem)', bold: true, rotate: 0, maxWidth: '34%' },
      // Kanan Dasar (Horizontal, tidak bold)
      { id: 's15', bottom: '3%', right: '4%', fontSize: 'clamp(1.2rem, 1.8vw, 2.1rem)', bold: false, rotate: 0, maxWidth: '40%' }
    ];

    // Fungsi Mengacak Array
    function shuffleArray(arr) {
      const copy = [...arr];
      for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
      }
      return copy;
    }

    // Inisialisasi Elemen Slot Catatan Latar Belakang
    function createBackgroundSlots() {
      backgroundQuotesLayer.innerHTML = '';
      backgroundSlotConfigs.forEach((cfg, idx) => {
        const slotEl = document.createElement('div');
        slotEl.id = 'bg-slot-' + idx;

        // Kelas dasar dan rotasi (rotasi sejak awal sudah 0 atau 90 derajat)
        let cls = 'bg-quote-slot ' + (cfg.rotate === 90 ? 'rotate-90' : 'rotate-0');
        if (cfg.bold) {
          cls += ' font-black';
        } else {
          cls += ' font-normal';
        }
        slotEl.className = cls;

        // Terapkan posisi dan style
        if (cfg.top) slotEl.style.top = cfg.top;
        if (cfg.bottom) slotEl.style.bottom = cfg.bottom;
        if (cfg.left) slotEl.style.left = cfg.left;
        if (cfg.right) slotEl.style.right = cfg.right;
        if (cfg.maxWidth) slotEl.style.maxWidth = cfg.maxWidth;
        if (cfg.width) slotEl.style.width = cfg.width;
        if (cfg.fontSize) slotEl.style.fontSize = cfg.fontSize;

        backgroundQuotesLayer.appendChild(slotEl);
      });
    }

    // Jalankan Animasi Fade-In / Fade-Out Catatan Santri Satu Per Satu
    function startBackgroundQuotesAnimation() {
      // Tampilkan layer latar belakang
      backgroundQuotesLayer.classList.remove('hidden');

      // Gelapkan latar belakang halaman menjadi hitam pekat murni seperti Contoh Latar.jpg
      pageBody.classList.remove('bg-gradient-to-br', 'from-slate-950', 'via-slate-900', 'to-emerald-950');
      pageBody.style.backgroundColor = '#000000';

      const slots = backgroundQuotesLayer.querySelectorAll('.bg-quote-slot');
      let quotePoolIndex = 0;
      const shuffledQuotes = shuffleArray(santriQuotesPool);

      function getNextQuote() {
        const q = shuffledQuotes[quotePoolIndex % shuffledQuotes.length];
        quotePoolIndex++;
        return q;
      }

      // Animasi siklus untuk setiap slot individual
      function runSlotCycle(slotEl) {
        // Ambil catatan berikutnya
        const text = getNextQuote();
        slotEl.textContent = text;

        // FADE IN
        slotEl.classList.add('active');

        // Durasi tampil di layar (antara 4.5 sampai 7 detik)
        const visibleDuration = 4500 + Math.random() * 2500;

        const toOut = setTimeout(() => {
          // FADE OUT
          slotEl.classList.remove('active');

          // Jeda saat pudar sebelum teks baru fade-in (1.5 detik)
          const toIn = setTimeout(() => {
            runSlotCycle(slotEl);
          }, 1500);

          backgroundSlotsTimeouts.push(toIn);
        }, visibleDuration);

        backgroundSlotsTimeouts.push(toOut);
      }

      // Mulai setiap slot dengan jeda berurutan (stagger) sehingga muncul SATU PER SATU
      slots.forEach((slotEl, idx) => {
        const staggerDelay = idx * 450; // Jeda 450ms per elemen
        const t = setTimeout(() => {
          runSlotCycle(slotEl);
        }, staggerDelay);
        backgroundSlotsTimeouts.push(t);
      });
    }

    // Bersihkan interval & timeout catatan latar
    function stopBackgroundQuotesAnimation() {
      backgroundSlotsTimeouts.forEach(t => clearTimeout(t));
      backgroundSlotsTimeouts = [];
      backgroundSlotsIntervals.forEach(i => clearInterval(i));
      backgroundSlotsIntervals = [];

      const slots = backgroundQuotesLayer.querySelectorAll('.bg-quote-slot');
      slots.forEach(slot => {
        slot.classList.remove('active');
        slot.textContent = '';
      });
      backgroundQuotesLayer.classList.add('hidden');

      // Kembalikan background semula
      pageBody.style.backgroundColor = '';
      pageBody.classList.add('bg-gradient-to-br', 'from-slate-950', 'via-slate-900', 'to-emerald-950');
    }

    // Fungsi Mulai Mengacak Foto
    function startShuffling() {
      photoFrameMusyrif.classList.add('slot-shuffling');
      photoFrameMusyrifah.classList.add('slot-shuffling');

      let idxM = 0;
      musyrifShuffleInterval = setInterval(() => {
        idxM = (idxM + 1) % musyrifList.length;
        imgMusyrif.src = musyrifList[idxM].foto;
        nameMusyrif.textContent = musyrifList[idxM].nama;
      }, 90);

      let idxMf = 0;
      musyrifahShuffleInterval = setInterval(() => {
        idxMf = (idxMf + 1) % musyrifahList.length;
        imgMusyrifah.src = musyrifahList[idxMf].foto;
        nameMusyrifah.textContent = musyrifahList[idxMf].nama;
      }, 90);
    }

    // Fungsi Berhenti Mengacak & Tampilkan Pemenang
    function stopShufflingAndShowWinners() {
      clearInterval(musyrifShuffleInterval);
      clearInterval(musyrifahShuffleInterval);

      photoFrameMusyrif.classList.remove('slot-shuffling');
      photoFrameMusyrifah.classList.remove('slot-shuffling');

      // Tampilkan Pemenang Musyrif
      imgMusyrif.src = winnerMusyrif.foto;
      nameMusyrif.textContent = winnerMusyrif.nama;
      badgeMusyrif.innerHTML = '🏆 MUSYRIF TERPILIH';
      badgeMusyrif.className = 'px-4 py-1 rounded-full text-xs font-black tracking-wider uppercase mb-4 bg-gradient-to-r from-amber-400 to-yellow-500 text-slate-950 shadow-lg';
      photoFrameMusyrif.className = 'w-48 h-48 sm:w-56 sm:h-56 rounded-2xl overflow-hidden border-4 border-amber-400 bg-slate-900 shadow-2xl mb-4 relative winner-glow transform scale-105 transition duration-500';
      cardMusyrif.className = 'w-full max-w-sm bg-slate-900/90 backdrop-blur-md border-2 border-amber-400/80 rounded-3xl p-6 flex flex-col items-center text-center shadow-2xl relative winner-glow transition duration-500 z-10';
      voteCountMusyrif.textContent = winnerMusyrif.suara;
      votesMusyrif.classList.remove('hidden');

      // Tampilkan Pemenang Musyrifah
      imgMusyrifah.src = winnerMusyrifah.foto;
      nameMusyrifah.textContent = winnerMusyrifah.nama;
      badgeMusyrifah.innerHTML = '🏆 MUSYRIFAH TERPILIH';
      badgeMusyrifah.className = 'px-4 py-1 rounded-full text-xs font-black tracking-wider uppercase mb-4 bg-gradient-to-r from-teal-400 to-emerald-400 text-slate-950 shadow-lg';
      photoFrameMusyrifah.className = 'w-48 h-48 sm:w-56 sm:h-56 rounded-2xl overflow-hidden border-4 border-teal-400 bg-slate-900 shadow-2xl mb-4 relative winner-glow transform scale-105 transition duration-500';
      cardMusyrifah.className = 'w-full max-w-sm bg-slate-900/90 backdrop-blur-md border-2 border-teal-400/80 rounded-3xl p-6 flex flex-col items-center text-center shadow-2xl relative winner-glow transition duration-500 z-10';
      voteCountMusyrifah.textContent = winnerMusyrifah.suara;
      votesMusyrifah.classList.remove('hidden');

      // Sembunyikan box countdown, tampilkan ucapan selamat
      countdownBox.classList.add('hidden');
      winnerBanner.classList.remove('hidden');
      winnerBanner.classList.add('flex');

      // Mainkan suara confetti
      if (confettiAudio) {
        confettiAudio.currentTime = 0;
        confettiAudio.play().catch(e => console.log('Audio play error:', e));
      }

      // Ledakkan Confetti dari pojok kiri bawah dan pojok kanan bawah
      triggerGrandConfettiBlast();

      // SETELAH CONFETTI SELESAI / MULAI MEREDA (sekitar 5 detik setelah meletus):
      // Munculkan catatan-catatan santri di latar belakang satu per satu (fade in & fade out)
      const tQuotes = setTimeout(() => {
        startBackgroundQuotesAnimation();
      }, 5000);
      backgroundSlotsTimeouts.push(tQuotes);
    }

    // Fungsi Confetti: Meledak dari pojok kiri bawah & pojok kanan bawah, berhamburan ke atas lalu banyak banget jatuh ke bawah
    function triggerGrandConfettiBlast() {
      if (typeof confetti !== 'function') return;

      const duration = 6.5 * 1000;
      const animationEnd = Date.now() + duration;

      // Ledakan masif pertama (Initial heavy burst)
      confetti({
        particleCount: 160,
        angle: 60,
        spread: 80,
        origin: { x: 0, y: 1 },
        startVelocity: 75,
        scalar: 1.25,
        gravity: 0.9,
        drift: 0.2,
        ticks: 450,
        colors: ['#10b981', '#f59e0b', '#3b82f6', '#ec4899', '#8b5cf6', '#ffffff']
      });

      confetti({
        particleCount: 160,
        angle: 120,
        spread: 80,
        origin: { x: 1, y: 1 },
        startVelocity: 75,
        scalar: 1.25,
        gravity: 0.9,
        drift: -0.2,
        ticks: 450,
        colors: ['#14b8a6', '#fbbf24', '#3b82f6', '#f43f5e', '#a855f7', '#ffffff']
      });

      // Ledakan berkesinambungan (continuous streams)
      const interval = setInterval(function() {
        const timeLeft = animationEnd - Date.now();
        if (timeLeft <= 0) {
          return clearInterval(interval);
        }

        const count = 40;

        confetti({
          particleCount: count,
          angle: 55 + Math.random() * 15,
          spread: 70,
          origin: { x: 0, y: 1 },
          startVelocity: 65 + Math.random() * 15,
          scalar: 1.15,
          gravity: 0.95,
          ticks: 380
        });

        confetti({
          particleCount: count,
          angle: 110 + Math.random() * 15,
          spread: 70,
          origin: { x: 1, y: 1 },
          startVelocity: 65 + Math.random() * 15,
          scalar: 1.15,
          gravity: 0.95,
          ticks: 380
        });
      }, 250);
    }

    // Fungsi Utama Alur Presentasi
    function startPresentation() {
      // Hentikan animasi catatan latar jika sebelumnya sedang berjalan
      stopBackgroundQuotesAnimation();
      createBackgroundSlots();

      // 1. Sembunyikan layar awal, tampilkan arena presentasi
      initialScreen.classList.add('hidden');
      presentationStage.classList.remove('hidden');
      presentationStage.classList.add('flex');

      // Reset state kartu jika diulang
      badgeMusyrif.innerHTML = 'Calon Musyrif';
      badgeMusyrif.className = 'px-4 py-1 rounded-full text-xs font-bold tracking-wider uppercase mb-4 bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
      photoFrameMusyrif.className = 'w-48 h-48 sm:w-56 sm:h-56 rounded-2xl overflow-hidden border-4 border-slate-700 bg-slate-900 shadow-xl mb-4 relative';
      cardMusyrif.className = 'w-full max-w-sm bg-slate-900/85 backdrop-blur-md border-2 border-emerald-500/30 rounded-3xl p-6 flex flex-col items-center text-center shadow-2xl relative transition duration-300 z-10';
      votesMusyrif.classList.add('hidden');

      badgeMusyrifah.innerHTML = 'Calon Musyrifah';
      badgeMusyrifah.className = 'px-4 py-1 rounded-full text-xs font-bold tracking-wider uppercase mb-4 bg-teal-500/20 text-teal-300 border border-teal-500/40';
      photoFrameMusyrifah.className = 'w-48 h-48 sm:w-56 sm:h-56 rounded-2xl overflow-hidden border-4 border-slate-700 bg-slate-900 shadow-xl mb-4 relative';
      cardMusyrifah.className = 'w-full max-w-sm bg-slate-900/85 backdrop-blur-md border-2 border-teal-500/30 rounded-3xl p-6 flex flex-col items-center text-center shadow-2xl relative transition duration-300 z-10';
      votesMusyrifah.classList.add('hidden');

      winnerBanner.classList.add('hidden');
      countdownBox.classList.add('hidden');

      // 2. Mulai pengacakan foto di sebelah kiri (Musyrif) & kanan (Musyrifah)
      startShuffling();

      // 3. Tampilkan teks pengantar: "Hitung bersama-sama ya...."
      introText.classList.remove('hidden');

      // Teks pengantar tampil selama 2.5 detik
      setTimeout(() => {
        introText.classList.add('hidden');

        // 4. Mulai hitung mundur: 10, 9, 8, ... sampai 1
        countdownBox.classList.remove('hidden');
        countdownBox.classList.add('flex');

        let currentCount = 10;
        countdownNumber.textContent = currentCount;
        triggerCountAnimation();

        const countInterval = setInterval(() => {
          currentCount--;
          if (currentCount >= 1) {
            countdownNumber.textContent = currentCount;
            triggerCountAnimation();
          } else {
            clearInterval(countInterval);
            // 5. Hitungan selesai: berhenti mengacak dan umumkan pemenang bersama confetti & audio
            stopShufflingAndShowWinners();
          }
        }, 1000);

      }, 2500);
    }

    // Animasi Pop pada setiap angka hitung mundur
    function triggerCountAnimation() {
      countdownNumber.classList.remove('animate-count');
      void countdownNumber.offsetWidth; // force reflow
      countdownNumber.classList.add('animate-count');
    }

    // Inisialisasi slot pada saat DOM siap
    document.addEventListener('DOMContentLoaded', () => {
      createBackgroundSlots();
    });

    // Event Listeners
    btnLihatHasil.addEventListener('click', startPresentation);

    if (btnReplay) {
      btnReplay.addEventListener('click', startPresentation);
    }
  </script>

</body>
</html>
