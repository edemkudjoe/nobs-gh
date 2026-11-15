<?php
declare(strict_types=1);

/**
 * A very small PDF writer for the credentials slip. No libraries and no extensions needed.
 * One A4 page, built-in fonts (Helvetica, Courier) and optional JPEG logos.
 */
final class MiniPdf
{
    private array $ops = [];
    private array $images = [];   // name => [bytes, width, height]
    public const W = 595.28;
    public const H = 841.89;

    /** Text is written in the PDF's Windows-1252 encoding. */
    private static function enc(string $s): string
    {
        $s = str_replace(["\u{2026}", "\u{2013}", "\u{2014}", "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"], ['...', '-', '-', "'", "'", '"', '"'], $s);
        $t = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        $t = $t === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $t;
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $t);
    }

    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        return sprintf('%.3f %.3f %.3f', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255);
    }

    /** $font: F1 Helvetica, F2 Helvetica-Bold, F3 Courier, F4 Courier-Bold. $y is measured from the TOP of the page. */
    public function text(float $x, float $y, string $s, string $font = 'F1', float $size = 11, string $color = '#1a1a1a'): void
    {
        $this->ops[] = sprintf("BT /%s %.2f Tf %s rg %.2f %.2f Td (%s) Tj ET", $font, $size, self::rgb($color), $x, self::H - $y, self::enc($s));
    }

    public function rect(float $x, float $y, float $w, float $h, ?string $fill = null, ?string $stroke = null, float $lw = 0.8): void
    {
        $op = ($fill ? self::rgb($fill) . ' rg ' : '') . ($stroke ? self::rgb($stroke) . ' RG ' . $lw . ' w ' : '');
        $paint = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $this->ops[] = sprintf("%s%.2f %.2f %.2f %.2f re %s", $op, $x, self::H - $y - $h, $w, $h, $paint);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#bbbbbb', float $lw = 0.8): void
    {
        $this->ops[] = sprintf("%s RG %.2f w %.2f %.2f m %.2f %.2f l S", self::rgb($color), $lw, $x1, self::H - $y1, $x2, self::H - $y2);
    }

    /** Add a JPEG file. Returns false (and draws nothing) if it cannot be read. */
    public function jpeg(string $path, float $x, float $y, float $h): float
    {
        $bytes = @file_get_contents($path);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (!$info || $info[2] !== IMAGETYPE_JPEG) {
            return 0.0;
        }
        $name = 'Im' . (count($this->images) + 1);
        $this->images[$name] = [$bytes, $info[0], $info[1]];
        $w = $h * $info[0] / $info[1];
        $this->ops[] = sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q", $w, $h, $x, self::H - $y - $h, $name);
        return $w;
    }

    /** Rough text width in points, safe for wrapping (a little wide on purpose). */
    public static function width(string $s, string $font, float $size): float
    {
        $mono = $font === 'F3' || $font === 'F4';
        return mb_strlen($s) * $size * ($mono ? 0.6 : ($font === 'F2' ? 0.56 : 0.52));
    }

    public function wrap(string $s, string $font, float $size, float $maxWidth): array
    {
        $lines = [];
        $cur = '';
        foreach (preg_split('/\s+/', trim($s)) as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($cur !== '' && self::width($try, $font, $size) > $maxWidth) {
                $lines[] = $cur;
                $cur = $word;
            } else {
                $cur = $try;
            }
        }
        return $cur === '' ? $lines : array_merge($lines, [$cur]);
    }

    public function output(string $title): string
    {
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $fonts = ['F1' => 'Helvetica', 'F2' => 'Helvetica-Bold', 'F3' => 'Courier', 'F4' => 'Courier-Bold'];
        $fontRes = '';
        $n = 5;
        foreach ($fonts as $key => $base) {
            $objs[$n] = "<< /Type /Font /Subtype /Type1 /BaseFont /$base /Encoding /WinAnsiEncoding >>";
            $fontRes .= "/$key $n 0 R ";
            $n++;
        }
        $imgRes = '';
        foreach ($this->images as $name => [$bytes, $w, $h]) {
            $objs[$n] = ["<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($bytes) . " >>", $bytes];
            $imgRes .= "/$name $n 0 R ";
            $n++;
        }
        $content = implode("\n", $this->ops);
        $objs[4] = ["<< /Length " . strlen($content) . " >>", $content];
        $objs[3] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents 4 0 R /Resources << /Font << %s>> /XObject << %s>> >> >>', self::W, self::H, $fontRes, $imgRes);
        $objs[$n] = '<< /Title (' . self::enc($title) . ') /Producer (National Organ and Blood Bank System) >>';
        $infoId = $n;

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        ksort($objs);
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($out);
            if (is_array($body)) {
                $out .= "$id 0 obj\n{$body[0]}\nstream\n{$body[1]}\nendstream\nendobj\n";
            } else {
                $out .= "$id 0 obj\n$body\nendobj\n";
            }
        }
        $xref = strlen($out);
        $count = max(array_keys($objs)) + 1;
        $out .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= "trailer\n<< /Size $count /Root 1 0 R /Info $infoId 0 R >>\nstartxref\n$xref\n%%EOF";
        return $out;
    }
}

/**
 * The credentials slip as a PDF. $s has the same shape that render_slip() takes:
 * title, heading, rows [label, value, class], steps.
 */
function slip_pdf(array $user, array $s): string
{
    $p = new MiniPdf();
    $L = 50.0;
    $R = MiniPdf::W - 50.0;
    $y = 48.0;

    // Logos (optional: the PDF is made without them if the files are missing)
    $x = $L;
    foreach (['nbs', 'moh', 'ghs'] as $n) {
        $w = $p->jpeg(__DIR__ . "/pdf-logos/$n.jpg", $x, $y, 44.0);
        if ($w > 0) {
            $x += $w + 8;
        }
    }
    $tx = $x > $L ? $x + 8 : $L;
    $p->text($tx, $y + 18, 'National Organ & Blood Bank System', 'F2', 15, '#1f3a5f');
    $p->text($tx, $y + 36, (string)$s['heading'], 'F1', 11, '#555555');
    $y += 62;
    $p->line($L, $y, $R, $y, '#1f3a5f', 1.5);
    $y += 22;

    // Warning box
    $p->rect($L, $y, $R - $L, 34, '#fdf0ee', '#c0392b', 0.8);
    $p->text($L + 10, $y + 14, 'This is the only time the password is shown.', 'F2', 10, '#8e2a1f');
    $p->text($L + 10, $y + 27, 'Keep this slip safe and give it only to the person named below.', 'F1', 9.5, '#8e2a1f');
    $y += 56;

    // Rows
    foreach ($s['rows'] as $r) {
        $big = !empty($r[2]) && str_contains((string)$r[2], 'big');
        $mono = !empty($r[2]) && str_contains((string)$r[2], 'mono');
        $p->text($L, $y, strtoupper((string)$r[0]), 'F2', 8, '#666666');
        $y += 6;
        if ($big) {
            $p->rect($L, $y, $R - $L, 38, '#eef3f8', '#1f3a5f', 1);
            $p->text($L + 12, $y + 26, (string)$r[1], 'F4', 20, '#111111');
            $y += 38 + 16;
        } else {
            $font = $mono ? 'F3' : 'F1';
            $size = $mono ? 11 : 12;
            $lines = $p->wrap((string)$r[1], $font, $size, $R - $L);
            $y += 13;
            foreach ($lines as $i => $ln) {
                $p->text($L, $y + $i * 15, $ln, $font, $size);
            }
            $y += max(0, count($lines) - 1) * 15 + 18;
        }
    }

    // Steps
    $y += 4;
    $p->line($L, $y, $R, $y, '#dddddd');
    $y += 20;
    $p->text($L, $y, 'What to do', 'F2', 11, '#1f3a5f');
    $y += 18;
    foreach ($s['steps'] as $i => $step) {
        $lines = $p->wrap((string)$step, 'F1', 10.5, $R - $L - 20);
        $p->text($L, $y, ($i + 1) . '.', 'F2', 10.5);
        foreach ($lines as $j => $ln) {
            $p->text($L + 18, $y + $j * 14, $ln, 'F1', 10.5);
        }
        $y += count($lines) * 14 + 6;
    }

    // Footer
    $p->line($L, MiniPdf::H - 70, $R, MiniPdf::H - 70, '#dddddd');
    $p->text($L, MiniPdf::H - 54, 'Issued ' . date('j F Y') . ' by ' . $user['full_name'] . '. Confidential.', 'F1', 8.5, '#666666');

    return $p->output((string)$s['title']);
}
