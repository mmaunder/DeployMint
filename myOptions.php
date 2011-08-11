<?php
/*
	Author: Mark Maunder <mmaunder@gmail.com>
	Author website: http://markmaunder.com/
	License: GPL 3.0
*/
?>

<div id="sdAjaxLoading" style="display: none; position: fixed; right: 1px; top: 1px; width: 100px; background-color: #F00; color: #FFF; font-size: 12px; font-family: Verdana, arial; font-weight: normal; text-align: center; z-index: 100; border: 1px solid #CCC;">Loading...</div>
<div class="wrap">
	<h2 class="depmintHead">DeployMint Options</h2>
	<div id="sdOptErrors">
	</div>
	<table class="form-table">
	<tr><th>Path to git:</th><td><input type="text" id="sdPathToGit" size="20" maxlength="255" value="<?php echo htmlspecialchars($opt['git']) ?>" /></td></tr>
	<tr><th>Path to mysql:</th><td><input type="text" id="sdPathToMysql" size="20" maxlength="255" value="<?php echo htmlspecialchars($opt['mysql']) ?>" /></td></tr>
	<tr><th>Path to mysqldump:</th><td><input type="text" id="sdPathToMysqldump" size="20" maxlength="255" value="<?php echo htmlspecialchars($opt['mysqldump']) ?>" /></td></tr>
	<tr><th>Path to a data directory for DeployMint:</th><td><input type="text" id="sdPathToDataDir" size="20" maxlength="255" value="<?php echo htmlspecialchars($opt['datadir']) ?>" /></td></tr>
	<tr><th>How many backups of your Wordpress database should we keep after each deploy:</th><td><input type="text" id="sdNumBackups" size="3" maxlength="255" value="<?php echo htmlspecialchars($opt['numBackups']) ?>" /></td></tr>
	</table>
	<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" onclick="deploymint.updateOptions(jQuery('#sdPathToGit').val(), jQuery('#sdPathToMysql').val(), jQuery('#sdPathToMysqldump').val(), jQuery('#sdPathToDataDir').val(), jQuery('#sdNumBackups').val()); return false;" />
	</p>
</div>
