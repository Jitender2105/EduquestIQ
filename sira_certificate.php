<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';
require_once __DIR__ . '/includes_sira.php';

$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$pdo = get_pdo();
$report = $attemptId > 0 ? sira_build_test_report($pdo, $attemptId) : null;
$reportError = null;

if ($attemptId <= 0) {
    $reportError = 'No attempt was selected.';
} elseif (!$report) {
    $reportError = 'We could not find a certificate-ready report for this attempt yet.';
}

function sira_certificate_medal(?int $rank): string
{
    if ($rank === null || $rank <= 0) {
        return '🎖️';
    }
    if ($rank === 1) {
        return '🥇';
    }
    if ($rank === 2) {
        return '🥈';
    }
    if ($rank === 3) {
        return '🥉';
    }
    return '🏅';
}

if ($reportError !== null) {
    ?>
    <div class="eq-page-head">
        <h2>SIRA Certificate</h2>
        <p class="subtitle">Certificates are generated after completed SIRA attempts.</p>
    </div>
    <div class="alert alert-warning">
        <?php echo htmlspecialchars($reportError); ?>
        <div class="mt-2">
            <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(url_for('tests.php')); ?>">Back to tests</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes_footer.php';
    return;
}

$overallScore = (float)$report['overall_score'];
$overallBand = sira_band($overallScore);
$comparison = $report['comparison'];
$overallRank = $comparison['overall']['rank'] !== null ? (int)$comparison['overall']['rank'] : null;
$studentName = (string)$report['attempt']['student_name'];
$testTitle = (string)$report['attempt']['test_title'];
$attemptDate = (string)$report['attempt']['attempt_date'];
$grade = trim((string)($report['attempt']['student_grade'] ?? ''));
$school = trim((string)($report['attempt']['student_school_name'] ?? ''));
$certificateId = 'SIRA-' . str_pad((string)$attemptId, 6, '0', STR_PAD_LEFT);
$certificatePath = url_for('sira_certificate.php?attempt_id=' . (int)$attemptId);
$certificateUrl = eq_absolute_url($certificatePath);
$shareText = $studentName . ' earned a SIRA Achievement Certificate on EduquestIQ for ' . $testTitle . ' with ' . number_format($overallScore, 1) . '% score.';
$encodedShareUrl = rawurlencode($certificateUrl);
$encodedShareText = rawurlencode($shareText . ' ' . $certificateUrl);
?>

<style>
    .eq-cert-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .eq-cert-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }
    .eq-cert-actions .btn {
        min-height: 38px;
        border-radius: 8px;
    }
    .eq-cert-copy-status {
        color: #198754;
        font-size: 0.86rem;
        font-weight: 800;
        min-height: 20px;
        width: 100%;
        text-align: right;
    }
    .eq-certificate {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        border: 10px solid #172045;
        background:
            radial-gradient(circle at 15% 20%, rgba(245,166,35,0.18), transparent 28%),
            radial-gradient(circle at 85% 12%, rgba(40,192,169,0.14), transparent 26%),
            linear-gradient(135deg, #fff, #f8faff);
        min-height: 720px;
        padding: 48px;
        box-shadow: 0 24px 52px rgba(37, 48, 99, 0.16);
        color: #121731;
    }
    .eq-certificate::before {
        content: "";
        position: absolute;
        inset: 20px;
        border: 2px solid rgba(105,70,232,0.22);
        border-radius: 8px;
        pointer-events: none;
    }
    .eq-cert-brand {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        position: relative;
        z-index: 1;
    }
    .eq-cert-brand img {
        height: 46px;
        width: auto;
        object-fit: contain;
    }
    .eq-cert-pill {
        border-radius: 999px;
        background: #fff;
        border: 1px solid rgba(30,43,92,0.1);
        color: #6946e8;
        font-weight: 800;
        padding: 8px 14px;
        font-size: 0.82rem;
    }
    .eq-cert-main {
        position: relative;
        z-index: 1;
        text-align: center;
        max-width: 860px;
        margin: 56px auto 34px;
    }
    .eq-cert-medal {
        width: 132px;
        height: 132px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        margin: 0 auto 20px;
        background: conic-gradient(#ffd66b, #f5a623, #fff0b8, #ffd66b);
        box-shadow: 0 18px 34px rgba(126, 83, 10, 0.22);
        font-size: 4rem;
    }
    .eq-cert-main h1 {
        font-size: clamp(2rem, 5vw, 4.2rem);
        font-weight: 800;
        margin: 0;
        color: #172045;
    }
    .eq-cert-main h2 {
        font-size: clamp(1.7rem, 4vw, 3rem);
        font-weight: 800;
        color: #6946e8;
        margin: 20px 0 12px;
    }
    .eq-cert-copy {
        color: #667089;
        font-size: 1.05rem;
        line-height: 1.75;
        margin: 0 auto;
        max-width: 760px;
    }
    .eq-cert-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 34px;
    }
    .eq-cert-stat {
        border-radius: 8px;
        background: rgba(255,255,255,0.86);
        border: 1px solid rgba(30,43,92,0.1);
        padding: 14px;
    }
    .eq-cert-stat strong {
        display: block;
        font-size: 1.35rem;
        font-family: 'Outfit', sans-serif;
    }
    .eq-cert-stat span {
        color: #667089;
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .eq-cert-footer {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin-top: 58px;
        align-items: end;
    }
    .eq-cert-line {
        border-top: 1px solid rgba(30,43,92,0.22);
        padding-top: 10px;
        color: #667089;
        font-size: 0.85rem;
    }
    @media (max-width: 767px) {
        .eq-certificate {
            padding: 24px;
            border-width: 6px;
        }
        .eq-cert-stats,
        .eq-cert-footer {
            grid-template-columns: 1fr;
        }
        .eq-cert-brand {
            display: block;
            text-align: center;
        }
        .eq-cert-brand .eq-cert-pill {
            display: inline-flex;
            margin-top: 12px;
        }
        .eq-cert-actions {
            justify-content: flex-start;
        }
        .eq-cert-copy-status {
            text-align: left;
        }
    }
    @media print {
        body {
            background: #fff !important;
        }
        .navbar,
        .eq-cert-toolbar,
        footer {
            display: none !important;
        }
        .container,
        .container-fluid {
            width: 100% !important;
            max-width: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .eq-certificate {
            box-shadow: none;
            min-height: 100vh;
            page-break-inside: avoid;
        }
    }
</style>

<div class="eq-cert-toolbar">
    <a href="<?php echo htmlspecialchars(url_for('sira_report.php?attempt_id=' . (int)$attemptId)); ?>" class="btn btn-link">&larr; Back to SIRA report</a>
    <div class="eq-cert-actions">
        <button type="button" class="btn btn-primary" id="downloadCertificateBtn">Download Image</button>
        <button type="button" class="btn btn-outline-primary" id="nativeShareBtn">Share Image</button>
        <button type="button" class="btn btn-outline-success" id="whatsappShareBtn">WhatsApp</button>
        <button type="button" class="btn btn-outline-primary" id="facebookShareBtn">Facebook</button>
        <button type="button" class="btn btn-outline-secondary" id="instagramShareBtn">Instagram</button>
        <button type="button" class="btn btn-outline-secondary" id="copyCertificateBtn">Copy Link</button>
        <div class="eq-cert-copy-status" id="certificateShareStatus" aria-live="polite"></div>
    </div>
</div>

<section class="eq-certificate">
    <div class="eq-cert-brand">
        <img src="<?php echo htmlspecialchars(url_for('assets/img/eduquestiq-logo-wide.png')); ?>" alt="EduquestIQ">
        <span class="eq-cert-pill"><?php echo htmlspecialchars($certificateId); ?></span>
    </div>

    <div class="eq-cert-main">
        <div class="eq-cert-medal"><?php echo sira_certificate_medal($overallRank); ?></div>
        <h1>Certificate of SIRA Achievement</h1>
        <p class="eq-cert-copy mt-3">This certificate is proudly awarded to</p>
        <h2><?php echo htmlspecialchars($studentName); ?></h2>
        <p class="eq-cert-copy">
            for completing <strong><?php echo htmlspecialchars($testTitle); ?></strong>
            and demonstrating measurable learning progress on EduquestIQ.
        </p>

        <div class="eq-cert-stats">
            <div class="eq-cert-stat"><strong><?php echo number_format($overallScore, 1); ?>%</strong><span>Overall Score</span></div>
            <div class="eq-cert-stat"><strong><?php echo $overallRank !== null ? '#' . (int)$overallRank : 'N/A'; ?></strong><span>Overall Rank</span></div>
            <div class="eq-cert-stat"><strong><?php echo htmlspecialchars($overallBand['label']); ?></strong><span>SIRA Band</span></div>
            <div class="eq-cert-stat"><strong><?php echo number_format((float)$report['earned_marks_total'], 1); ?></strong><span>Marks Earned</span></div>
        </div>
    </div>

    <div class="eq-cert-footer">
        <div class="eq-cert-line">
            Date: <?php echo htmlspecialchars($attemptDate); ?>
        </div>
        <div class="eq-cert-line">
            <?php echo htmlspecialchars($grade !== '' ? $grade : 'Grade not assigned'); ?>
            <?php echo $school !== '' ? ' · ' . htmlspecialchars($school) : ''; ?>
        </div>
        <div class="eq-cert-line">
            EduquestIQ SIRA Assessment
        </div>
    </div>
</section>

<script src="<?php echo htmlspecialchars(url_for('assets/js/html2canvas.min.js')); ?>"></script>
<script>
(function () {
    const certificateUrl = <?php echo json_encode($certificateUrl, JSON_UNESCAPED_SLASHES); ?>;
    const shareText = <?php echo json_encode($shareText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const certificateId = <?php echo json_encode($certificateId, JSON_UNESCAPED_SLASHES); ?>;
    const status = document.getElementById('certificateShareStatus');

    function setStatus(message) {
        if (!status) return;
        status.textContent = message;
        window.setTimeout(function () {
            if (status.textContent === message) {
                status.textContent = '';
            }
        }, 3500);
    }

    function copyShareText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        return Promise.resolve();
    }

    async function renderCertificateBlob() {
        const certificate = document.querySelector('.eq-certificate');
        if (!certificate) {
            throw new Error('Certificate not found.');
        }
        if (typeof html2canvas !== 'function') {
            throw new Error('Image renderer is still loading. Please try again.');
        }
        const canvas = await html2canvas(certificate, {
            backgroundColor: '#ffffff',
            scale: Math.min(2, window.devicePixelRatio || 1.5),
            useCORS: true
        });
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('Could not create certificate image.'));
                }
            }, 'image/png', 1);
        });
    }

    function downloadBlob(blob) {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = certificateId + '-certificate.png';
        document.body.appendChild(link);
        link.click();
        window.setTimeout(function () {
            URL.revokeObjectURL(link.href);
            link.remove();
        }, 1000);
    }

    async function shareImage(fallbackUrl) {
        setStatus('Creating certificate image...');
        const blob = await renderCertificateBlob();
        const file = new File([blob], certificateId + '-certificate.png', { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] }) && navigator.share) {
            await navigator.share({
                title: 'SIRA Achievement Certificate',
                text: shareText,
                files: [file]
            });
            setStatus('Certificate image shared.');
            return;
        }
        downloadBlob(blob);
        await copyShareText(shareText + ' ' + certificateUrl);
        setStatus('Image downloaded. Caption/link copied.');
        if (fallbackUrl) {
            window.open(fallbackUrl, '_blank', 'noopener');
        }
    }

    document.getElementById('downloadCertificateBtn')?.addEventListener('click', async function () {
        try {
            setStatus('Creating certificate image...');
            downloadBlob(await renderCertificateBlob());
            setStatus('Certificate image downloaded.');
        } catch (error) {
            setStatus(error.message || 'Could not download image.');
        }
    });

    document.getElementById('nativeShareBtn')?.addEventListener('click', async function () {
        try {
            await shareImage('');
        } catch (error) {
            if (error && error.name !== 'AbortError') {
                setStatus(error.message || 'Could not share image.');
            }
        }
    });

    document.getElementById('whatsappShareBtn')?.addEventListener('click', async function () {
        try {
            await shareImage('https://api.whatsapp.com/send?text=' + encodeURIComponent(shareText + ' ' + certificateUrl));
        } catch (error) {
            if (error && error.name !== 'AbortError') {
                setStatus(error.message || 'Could not share image.');
            }
        }
    });

    document.getElementById('facebookShareBtn')?.addEventListener('click', async function () {
        try {
            await shareImage('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(certificateUrl));
        } catch (error) {
            if (error && error.name !== 'AbortError') {
                setStatus(error.message || 'Could not share image.');
            }
        }
    });

    document.getElementById('copyCertificateBtn')?.addEventListener('click', async function () {
        await copyShareText(certificateUrl);
        setStatus('Certificate link copied.');
    });

    document.getElementById('instagramShareBtn')?.addEventListener('click', async function () {
        try {
            await shareImage('https://www.instagram.com/');
        } catch (error) {
            if (error && error.name !== 'AbortError') {
                setStatus(error.message || 'Could not share image.');
            }
        }
    });
})();
</script>

<?php
require_once __DIR__ . '/includes_footer.php';
