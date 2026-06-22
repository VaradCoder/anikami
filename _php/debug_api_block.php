<?php
// Small debug helper to display internal API failures on UI pages.
// Expects: $dbg array created by app_debug_api_context().

if (!isset($dbg) || !is_array($dbg)) {
    return;
}

$endpoint = (string)($dbg['endpoint'] ?? '');
$meta = (array)($dbg['meta'] ?? []);
$error = $dbg['error'] ?? null;
$raw = $dbg['raw'] ?? null;

if (!empty($error)) :
?>
<div class="catalog-empty" style="margin:16px 0; border:1px dashed rgba(255,0,0,.35); background:rgba(255,0,0,.05); padding:14px 16px;">
    <h3 style="color:#ff3b30; margin:0 0 8px;">Internal API error</h3>
    <div style="font-family:monospace; font-size:12px; color:#b00020;">
        <div><b>Endpoint:</b> <?= app_e($endpoint) ?></div>
        <?php if (!empty($meta)) : ?>
            <div style="margin-top:6px;"><b>Meta:</b> <?= app_e(json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></div>
        <?php endif; ?>
        <?php if (is_array($error)) : ?>
            <div style="margin-top:6px;"><b>Error:</b> <?= app_e(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></div>
        <?php else : ?>
            <div style="margin-top:6px;"><b>Error:</b> <?= app_e((string)$error) ?></div>
        <?php endif; ?>

        <?php if (is_string($raw) && strlen($raw) > 0) : ?>
            <details style="margin-top:8px;">
                <summary style="cursor:pointer; color:#b00020;">Raw response (truncated)</summary>
                <pre style="white-space:pre-wrap; word-break:break-word; max-height:180px; overflow:auto; background:rgba(0,0,0,.2); padding:8px; border-radius:8px; color:#fff;"><?= app_e(substr($raw, 0, 1200)) ?></pre>
            </details>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


