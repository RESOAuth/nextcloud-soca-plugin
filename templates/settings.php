<?php
/** @var array $_ */
?>
<section class="section" aria-labelledby="soca-settings-title">
<h2 id="soca-settings-title">SOCA sign-in</h2>
<p>Configuration comes from SOCA_CONFIG_FILE. All values are locked to deployment configuration. Secrets remain in deployment files.</p>
<p>Check public documents, then run a candidate test with the current local administrator session. Tests apply admission without creating or linking accounts.</p>
<pre aria-label="Effective SOCA configuration and evidence"><?php p($_['status']); ?></pre>
<form method="post" action="<?php p($_['check']); ?>"><input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>"><button type="submit">Check public documents</button></form>
<form method="post" action="<?php p($_['test']); ?>"><input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>"><button type="submit">Test candidate sign-in</button></form>
<p>Recovery: use the ordinary Nextcloud password form. Set SOCA_EMERGENCY_DISABLE=1 and restart PHP workers to stop SOCA immediately.</p>
</section>
