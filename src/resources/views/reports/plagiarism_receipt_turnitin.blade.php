<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Plagiarism Receipt</title>
    <style>
        * {
            box-sizing: border-box
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 0;
            background: #f7f8fb;
            color: #1f2a44
        }

        a {
            color: #2563eb;
            text-decoration: none
        }

        .wrap {
            padding: 18px
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px
        }

        .brand {
            font-weight: 800;
            color: #2563eb
        }

        .meta {
            font-size: 11px;
            line-height: 1.5;
            text-align: right;
            color: #67728a
        }

        /* Layout 2 kolom (sidebar + main) */
        .grid {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 16px
        }

        .card {
            background: #fff;
            border: 1px solid #e7e9f2;
            border-radius: 12px
        }

        .card .body {
            padding: 14px
        }

        .subtle {
            color: #6e7892;
            font-size: 12px
        }

        .h1 {
            font-size: 18px;
            margin: 0 0 6px
        }

        .h2 {
            font-size: 14px;
            margin: 0 0 6px
        }

        /* Sidebar */
        .big {
            font-size: 36px;
            font-weight: 800
        }

        .pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            color: #fff;
            font-size: 11px
        }

        .bar {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #eef1f6;
            overflow: hidden;
            margin-top: 6px
        }

        .bar>span {
            display: block;
            height: 100%
        }

        .kv {
            font-size: 12px;
            line-height: 1.5;
            margin-top: 8px
        }

        .kv b {
            color: #1f2a44
        }

        /* Legend sumber */
        .legend {
            display: flex;
            flex-direction: column;
            gap: 10px
        }

        .item {
            display: flex;
            gap: 10px
        }

        .sw {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            border: 1px solid #d8dbe6;
            margin-top: 3px
        }

        .it-title {
            font-weight: 600
        }

        .meter {
            height: 8px;
            background: #eef1f6;
            border-radius: 999px;
            overflow: hidden
        }

        .meter>span {
            display: block;
            height: 100%
        }

        /* Dokumen disorot */
        .doc {
            border: 1px dashed #cfd6e6;
            border-radius: 10px;
            background: #fff;
            padding: 12px
        }

        .doc .content {
            white-space: pre-wrap;
            line-height: 1.7;
            font-size: 12px
        }

        /* Highlight span warna datang dari server -> inline style */

        /* Catatan */
        .note {
            font-size: 11px;
            color: #6e7892
        }
    </style>
</head>

<body>
    <div class="wrap">

        <div class="header">
            <div>
                <div class="brand">Plagiarism Receipt</div>
                <div class="subtle">Gaya ringkasan originality — versi kustom</div>
            </div>
            <div class="meta">
                Dibuat: {{ $generated_at->format('d M Y H:i') }} WIB<br>
                Pengguna: {{ $user->name }}<br>
                Dokumen: <b>{{ $document->title }}</b>
            </div>
        </div>

        <div class="grid">
            {{-- ========== SIDEBAR ========== --}}
            <div class="card">
                <div class="body">
                    <div class="subtle">Similarity Index</div>
                    <div class="big" style="color:{{ $risk['hex'] }}">{{ number_format($overall, 2) }}%</div>
                    <span class="pill" style="background:{{ $risk['hex'] }}">{{ $risk['label'] }}</span>

                    <div class="bar"><span
                            style="width:{{ min(100, max(0, $overall)) }}%;background:{{ $risk['hex'] }}"></span></div>

                    <div class="kv">
                        Ditutup oleh gabungan sumber:<br>
                        <b>{{ $covered_count }}</b> dari <b>{{ $token_count }}</b> token<br>
                        Sumber dianalisis: <b>{{ count($sources) }}</b>
                    </div>

                    @if (!empty($sources))
                        <div class="kv" style="margin-top:10px">
                            <div class="subtle">Top Sumber</div>
                            @foreach ($sources as $i => $s)
                                @break($i === 3)
                                <div style="margin:6px 0">
                                    <div style="display:flex;justify-content:space-between;gap:8px">
                                        <span><b>[S{{ $i + 1 }}]</b> {{ Str::limit($s['title'], 26) }}</span>
                                        <b>{{ number_format($s['contrib_pct'], 2) }}%</b>
                                    </div>
                                    <div class="meter">
                                        <span
                                            style="width:{{ $s['contrib_pct'] }}%;background:{{ $s['color'] }}"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- ========== MAIN CONTENT ========== --}}
            <div class="card">
                <div class="body">
                    <div class="h1">Rincian Sumber</div>
                    @if (empty($sources))
                        <div class="subtle">Tidak ada sumber terdeteksi.</div>
                    @else
                        <div class="legend">
                            @foreach ($sources as $i => $s)
                                <div class="item">
                                    <div class="sw" style="background:{{ $s['color'] }}"></div>
                                    <div>
                                        <div class="it-title">[S{{ $i + 1 }}] {{ $s['title'] }}</div>
                                        <div class="subtle">
                                            Kontribusi <b>{{ number_format($s['contrib_pct'], 2) }}%</b>
                                            · Jaccard {{ number_format((float) ($s['jaccard'] ?? 0), 2) }}%
                                            · Cosine {{ number_format((float) ($s['cosine'] ?? 0), 2) }}%
                                            @if (!empty($s['pdf_url']))
                                                · <a href="{{ $s['pdf_url'] }}">PDF</a>
                                            @endif
                                            @if (!empty($s['url']))
                                                · <a href="{{ $s['url'] }}">Halaman</a>
                                            @endif
                                        </div>
                                        @if (!empty($s['snippets']))
                                            @foreach ($s['snippets'] as $snip)
                                                <div class="subtle">— {!! $snip !!}</div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div style="height:10px"></div>
                    <div class="h2">Dokumen yang Diunggah (Teks Disorot)</div>
                    <div class="doc">
                        <div class="content">{!! $colored_html !!}</div>
                    </div>

                    <div class="note" style="margin-top:8px">
                        Penandaan dilakukan per-segmen karakter (berbasis n-gram 3 kata). Area tanpa warna = tidak
                        terindikasi.
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:12px" class="card">
            <div class="body">
                <div class="h2">Catatan Metode</div>
                <div class="note">
                    • Overall dihitung dari gabungan area yang beririsan dengan sumber (union coverage).<br>
                    • Jaccard & Cosine ditampilkan sebagai informasi pendamping.<br>
                    • Warna di legenda identik dengan warna highlight pada teks.
                </div>
            </div>
        </div>

    </div>
</body>

</html>
