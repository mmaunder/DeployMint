<?php
/*
	Author: Mark Maunder <mmaunder@gmail.com>
	Author website: http://markmaunder.com/
	License: GPL 3.0
*/
?>

<div id="sdAjaxLoading" style="display: none; position: fixed; right: 1px; top: 1px; width: 100px; background-color: #F00; color: #FFF; font-size: 12px; font-family: Verdana, arial; font-weight: normal; text-align: center; z-index: 100; border: 1px solid #CCC;">Loading...</div>
<div class="wrap">
	<h2 class="depmintHead">Emergency Revert</h2>
	<p style="width: 500px;">
		<b style="color: #F00;">Only to be used in emergencies. This will overwrite your entire Wordpress MU Network of blogs and revert it to the state it was in when the backup was taken.</b>
		This is a list of recent deployments. Each time we deploy, we make a full backup of your entire Wordpress database. 
		If for some reason a deployment goes awry, you can revert to your original pristine database by clicking "Revert" below.
		<br /><br />
		<b>NOTE:</b> Reverting to a previous database will blow away any comments, blog entries, pages, stats or any other data created since you deployed. The only data that is preserved is the data of the DeployMint system itself.
	</p>
	<p>
		<?php if(sizeof($dbs) > 0){ ?>
		<table class="deploymintTable">
		<tr>
			<td class="deploymintCellHeading deploymintPadCell"><input type="checkbox" name="selAll" onclick="var state = jQuery(this).prop('checked'); jQuery('.depToDelete').each(function(){ jQuery(this).prop('checked', state); });" /></td>
			<td class="deploymintCellHeading deploymintPadCell">Deployment Date</td>
			<td class="deploymintCellHeading deploymintPadCell">Project</td>
			<td class="deploymintCellHeading deploymintPadCell">Snapshot</td>
			<td class="deploymintCellHeading deploymintPadCell">Deployed From</td>
			<td class="deploymintCellHeading deploymintPadCell">Deployed To</td>
			<td class="deploymintCellHeading deploymintPadCell"></td>
		</tr>

		<?php 
		foreach($dbs as $db){
		?>
			<tr>
			<td class="deploymintPadCell"><input type="checkbox" name="selAll" class="depToDelete" value="<?php echo $db['dbname'] ?>" /></td>
			<td class="deploymintPadCell"><?php echo $db['deployTimeH'] ?></td>
			<td class="deploymintPadCell"><?php echo $db['projectName'] ?></td>
			<td class="deploymintPadCell"><?php echo $db['snapshotName'] ?></td>
			<td class="deploymintPadCell"><?php echo $db['deployFrom'] ?></td>
			<td class="deploymintPadCell"><?php echo $db['deployTo'] ?></td>
			<td class="deploymintPadCell"><a href="#" onclick="deploymint.undoDeploy('<?php echo $db['dbname'] ?>'); return false;">Revert Wordpress Installation to state prior to this deployment</a></td>
			</tr>

		<?php
		}
		?>
		</table>
		<input type="button" class="button-primary" value="Delete selected backups" onclick="var allVals = []; jQuery('.depToDelete:checked').each(function(){ allVals.push(jQuery(this).val()); }); deploymint.deleteBackups(allVals); return false;" />
		<?php } else { ?>
		<div style="font-weight: bold; margin: 20px 0 0 20px; width: 500px;">	
		You have not deployed any snapshots yet or you don't have any backups available. Once you deploy your first snapshot
		you will see a backup database appear here that you can restore if neccesary.
		</div>

		<?php } ?>
	</p>
		
</div>

