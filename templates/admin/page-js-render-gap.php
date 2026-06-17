<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$snap_table   = SEOB_Database::js_gap_snapshots_table();
$result_table = SEOB_Database::js_gap_results_table();
$total_snaps  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$snap_table}" );
$snaps_24h    = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$snap_table} WHERE received_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ) )
);
$last_analyzed = $wpdb->get_var( "SELECT MAX(analyzed_at) FROM {$result_table}" );
?>
<div class="wrap seob-wrap">
<h1>JS Render Gap detektor</h1>
<p style="color:#555;">Porovnává surové HTML (jak ho vidí Google bez JS) s vyrenderovaným DOM (jak stránku vidí uživatel). Velký rozdíl = riziko výpadku indexace.</p>

<?php if ( $total_snaps === 0 ): ?>
<div class="notice notice-warning" style="margin:12px 0;">
  <p><strong>Žádné snapshoty zatím.</strong> Beacon skript je aktivní – data se začnou sbírat od reálných návštěvníků frontendu. Po přijetí prvních snapshotů klikněte "Spustit analýzu".</p>
</div>
<?php elseif ( $snaps_24h === 0 ): ?>
<div class="notice notice-warning" style="margin:12px 0;">
  <p>Za posledních 24 h nepřišel žádný snapshot. Zkontrolujte, zda beacon není blokovaný consent nástrojem nebo cache pluginem.</p>
</div>
<?php endif; ?>

<div id="seob-jsgap-error" class="notice notice-error" style="display:none;margin:8px 0;"></div>
<div id="seob-jsgap-success" class="notice notice-success" style="display:none;margin:8px 0;"></div>

<!-- ── Statistiky ─────────────────────────────────────────── -->
<div class="seob-card" style="margin-bottom:16px;">
  <h2 style="margin-top:0;">Přehled</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin-bottom:12px;">
    <div class="seob-stat-box">
      <span class="seob-stat-num" id="seob-jsgap-total-snaps"><?php echo esc_html( $total_snaps ); ?></span>
      <span class="seob-stat-label">Snapshotů celkem</span>
    </div>
    <div class="seob-stat-box">
      <span class="seob-stat-num" id="seob-jsgap-snaps-24h"><?php echo esc_html( $snaps_24h ); ?></span>
      <span class="seob-stat-label">Snapshotů za 24 h</span>
    </div>
    <div class="seob-stat-box seob-stat-error" id="seob-jsgap-critical-box">
      <span class="seob-stat-num" id="seob-jsgap-critical">—</span>
      <span class="seob-stat-label">Kritické (skóre ≥ 50)</span>
    </div>
    <div class="seob-stat-box seob-stat-warn" id="seob-jsgap-warning-box">
      <span class="seob-stat-num" id="seob-jsgap-warning">—</span>
      <span class="seob-stat-label">Varování (20–49)</span>
    </div>
    <div class="seob-stat-box seob-stat-ok" id="seob-jsgap-ok-box">
      <span class="seob-stat-num" id="seob-jsgap-ok">—</span>
      <span class="seob-stat-label">V pořádku (&lt; 20)</span>
    </div>
    <div class="seob-stat-box">
      <span class="seob-stat-num" id="seob-jsgap-avg">—</span>
      <span class="seob-stat-label">Průměrné skóre</span>
    </div>
  </div>

  <?php if ( $last_analyzed ): ?>
  <p style="color:#777;font-size:12px;">Poslední analýza: <?php echo esc_html( $last_analyzed ); ?> UTC</p>
  <?php endif; ?>

  <button id="seob-jsgap-run-btn" class="button button-primary">&#9654; Spustit analýzu (na pozadí)</button>
</div>

<!-- ── Výsledky ───────────────────────────────────────────── -->
<div class="seob-card">
  <h2 style="margin-top:0;">Výsledky per URL</h2>

  <div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;">
    <strong>Filtr:</strong>
    <button class="button seob-jsgap-filter button-primary" data-filter="all">Vše</button>
    <button class="button seob-jsgap-filter" data-filter="critical">Kritické</button>
    <button class="button seob-jsgap-filter" data-filter="warning">Varování</button>
    <button class="button seob-jsgap-filter" data-filter="ok">OK</button>
  </div>

  <div id="seob-jsgap-table-wrap">
    <p style="color:#888;">Klikněte "Spustit analýzu" pro načtení výsledků, nebo počkejte na týdenní cron.</p>
  </div>
  <div id="seob-jsgap-pagination" style="margin-top:10px;display:none;"></div>
</div>

<!-- ── Legenda skóre ─────────────────────────────────────── -->
<div class="seob-card" style="margin-top:16px;">
  <h2 style="margin-top:0;">Jak číst Gap Skóre</h2>
  <table class="widefat" style="max-width:600px;">
    <thead><tr><th>Skóre</th><th>Závažnost</th><th>Co to znamená</th></tr></thead>
    <tbody>
      <tr><td>0–19</td><td><span class="seob-ok">&#10003; OK</span></td><td>Stránka dobře indexovatelná i bez JS</td></tr>
      <tr><td>20–49</td><td><span class="seob-warn">&#9888; Varování</span></td><td>Část obsahu závislá na JS – doporučeno zkontrolovat</td></tr>
      <tr><td>50–100</td><td><span class="seob-error">&#10005; Kritické</span></td><td>Klíčový obsah (H1, JSON-LD, meta) chybí v raw HTML – riziko výpadku indexace</td></tr>
    </tbody>
  </table>
</div>

</div>
