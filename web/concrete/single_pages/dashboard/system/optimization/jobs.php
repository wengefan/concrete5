<?php defined('C5_EXECUTE') or die("Access Denied.");
/* @var $h ConcreteDashboardHelper */
$h = Loader::helper('concrete/dashboard');
/* @var $ih ConcreteInterfaceHelper */
$ih = Loader::helper('concrete/interface');
/* @var $form FormHelper */
$form = Loader::helper('form');
/* @var $jh JsonHelper */
$jh = Loader::helper('json');

?>
<style type="text/css">
</style>

<?=$h->getDashboardPaneHeaderWrapper(t('Automated Jobs'), false, false);?>
<? if (count($installedJobs) > 0) { ?>

<table class="table table-striped" id="ccm-jobs-list">
	<thead>
	<tr>
		<th><?=t('ID')?></th>
		<th><?=t('Name')?></th>
		<th><?=t('Last Run')?></th>
		<th><?=t('Results of Last Run')?></th>
		<th></th>
	</tr>
	</thead>
	<tbody>
	<? foreach($installedJobs as $j) { ?>
		<tr>
			<td><?=$j->getJobID()?></td>
			<td><i class="icon-question-sign" title="<?=$j->getJobDescription()?>"></i> <?=$j->getJobName()?></td>
			<td class="jDateLastRun"><?
				if ($j->getJobStatus() == 'RUNNING') {
					$runtime = date(DATE_APP_GENERIC_TS, strtotime($j->getJobDateLastRun()));
					echo ("<strong>");
					echo t("Currently Running (Since %s)", $runtime);					
					echo ("</strong>");
				} else if($j->getJobDateLastRun() == '' || substr($j->getJobDateLastRun(), 0, 4) == '0000') {
					echo t('Never');
				} else {
					$runtime = date(DATE_APP_GENERIC_MDY . t(' \a\t ') . DATE_APP_GENERIC_TS, strtotime($j->getJobDateLastRun()) );
					echo $runtime;
				}
			?></td>
			<td class="jLastStatusText"><?=$j->getJobLastStatusText()?></td>
			<td><button data-jID="<?=$j->getJobID()?>" data-jSupportsQueue="<?=$j->supportsQueue()?>" data-jName="<?=$j->getJobName()?>" class="btn-run-job btn"><i class="icon-play"></i> <?=t('Run')?></button></td>
		</tr>

	<? } ?>
	</tbody>
</table>
	
<? } else { ?>
	<p><?=t('You have no jobs installed.')?></p>
<? } ?>

<? if (count($availableJobs) > 0) { ?>
	<h3><?=t('Awaiting Installation')?></h3>

<? } ?>

<script type="text/javascript">
jQuery.fn.pulseRow = function() {

}

$(function() {
	$('.icon-question-sign').tooltip();
	$('.btn-run-job').on('click', $('#ccm-jobs-list'), function() {
		var row = $(this).parent().parent();
		row.pulseRow();
		var jSupportsQueue = $(this).attr('data-jSupportsQueue');
		var jID = $(this).attr('data-jID');
		var jName = $(this).attr('data-jName');
		var params = [
			{'name': 'auth', 'value': '<?=$auth?>'},
			{'name': 'jID', 'value': jID}
		];
		if (jSupportsQueue) {
			ccm_triggerProgressiveOperation(
				CCM_TOOLS_PATH + '/jobs/run_single',
				params,
				jName, function() {
					$('.ui-dialog-content').dialog('close');
					//row.find('.jLastStatusText').html(json.message);
					//row.find('.jDateLastRun').html(json.jDateLastRun);
					row.removeClass().addClass('warning');
	//				row.addClass(json.error == 0 ? 'green' : 'red');
				}
			);
		} else {
			$.ajax({ 
				url: CCM_TOOLS_PATH + '/jobs/run_single',
				data: params,
				dataType: 'json',
				cache: false,
				success: function(json) {
					row.removeClass().addClass('success');
				}
			});
		}
	});
});
</script>
<?=$h->getDashboardPaneFooterWrapper();?>

<?
/*
?>
<style type="text/css">
.run-all, .run-task { height: 16px; width: 16px; display: block; }
.run-indicator {
	height: 16px; width: 16px; display: none;
	background: url("<?=ASSETS_URL_IMAGES?>/throbber_white_16.gif") no-repeat transparent;
}

.running .run-indicator {
	display: block;
}

.running .run-all, 
.running .run-task {
	display: none;
}

.run-task {
	background: url("<?=ASSETS_URL_IMAGES?>/ui-icons_222222_256x240.png") no-repeat 0 -160px transparent;
}

.run-all {
	background: url("<?=ASSETS_URL_IMAGES?>/ui-icons_222222_256x240.png") no-repeat -32px -160px transparent;
}
</style>
<script>
jQuery(function($) {
	$('.btn.remove').bind('click', function(e) {
		if (!confirm(<?=$jh->encode(t("Are you sure you want to uninstall this job?"))?>)) {
			e.preventDefault();
		}
	});

	function runTaskForRow(row, jobId, jobTitle, supportsQueue, cb) {
		row.addClass('running');
		if (supportsQueue) {
			ccm_triggerProgressiveOperation(
				CCM_TOOLS_PATH + '/jobs', //?auth=<?=$auth?>&jID=' + jobId,
				[
					{'name': 'auth', 'value': '<?=$auth?>'},
					{'name': 'jID', 'value': jobId}
				],
				jobTitle, function() {
					$('.ui-dialog-content').dialog('close');
					row.removeClass('running green red');
					row.find('.jLastStatusText').html(json.message);
					row.find('.jDateLastRun').html(json.jDateLastRun);
					row.addClass(json.error == 0 ? 'green' : 'red');
					if (cb && $.isFunction(cb)) {
						cb(row, json, jobId);
					}

				}
			);

		} else { 
			$.ajax({ 
				url: CCM_TOOLS_PATH + '/jobs?auth=<?=$auth?>&jID=' + jobId,
				dataType: 'json',
				cache: false,
				success: function(json) {
					row.removeClass('running green red');
					row.find('.jLastStatusText').html(json.message);
					row.find('.jDateLastRun').html(json.jDateLastRun);
					row.addClass(json.error == 0 ? 'green' : 'red');
					if (cb && $.isFunction(cb)) {
						cb(row, json, jobId);
					}
				}
			});
		}
	}

	$('.run-task[data-jobId]').bind('click', function(e) {
		e.preventDefault();
		var $this = $(this),
			row = $this.closest('tr');
		runTaskForRow(row, $this.attr('data-jobId'), $this.attr('data-job-title'), $this.attr('data-supports-queue'));
	});

	$('.run-all').bind('click', function(e) {
		var $this = $(this),
			table = $this.closest('table'),
			links = table.find('tbody .run-task[data-jobId]'),
			jobs = links.map(function() {
					return {
						jobId : $(this).attr('data-jobId'),
						supportsQueue: $(this).attr('data-supports-queue'),
						jobTitle: $(this).attr('data-job-title'),
						row : $(this).closest('tr')
					};
			}).get(),
			next = function() {
				var job = jobs.shift();
				if (job) {
					runTaskForRow(job.row, job.jobId, job.jobTitle, job.supportsQueue, next);
				} else {
					table.removeClass('running');
				}
			};

		if (!table.hasClass('running')) {
			e.preventDefault();
			table.addClass('running');
			next();
		}
	});
});
</script>
<?=$h->getDashboardPaneHeaderWrapper(t('Automated Jobs'), false, false, false);?>
<div class="ccm-pane-body">
<?if ($jobList->numRows() == 0):?>
<?=t('You currently have no jobs installed.')?>
<?else:?>
<table class="table table-striped">
<thead>
<tr>
	<th><a class="run-all" href="<?=BASE_URL.$this->url('/tools/required/jobs?auth='.$auth.'&debug=1')?>" title="<?=t('Run all')?>"></a><span class="run-indicator"></span></th>
	<th><?=t('ID')?></th>
	<th><?=t('Name')?></th>
	<th><?=t('Description')?></th>
	<th><?=t('Last Run')?></th>
	<th><?=t('Results of Last Run')?></th>
	<th></th>
</tr>
</thead>
<tbody>
<? $jobrunning = false; ?>
<?foreach ($jobList as $job):
	$j = Job::getByHandle($job['jHandle']);
?>
<tr <? if ($job['jStatus'] == 'RUNNING') {
	
	$jobrunning = true;?>class="running" <? } ?>>
	<td><a class="run-task" title="<?=t('Run')?>" href="<?=BASE_URL.$this->url('/tools/required/jobs?auth='.$auth.'&jID='.$job['jID'])?>" data-job-title="<?=$job['jName']?>" data-supports-queue="<?=$j->supportsQueue()?>" data-jobId="<?=$job['jID']?>"></a><span class="run-indicator"></span></td>
	<td><?=$job['jID']?></td>
	<td><?=t($job['jName'])?></td>
	<td><?=t($job['jDescription'])?></td>
	<td class="jDateLastRun"><?
	if ($job['jStatus'] == 'RUNNING') {
		$runtime = date(DATE_APP_GENERIC_TS, strtotime($job['jDateLastRun']));
		echo ("<strong>");
		echo t("Currently Running (Since %s)", $runtime);					
		echo ("</strong>");
	} else if($job['jDateLastRun'] == '' || substr($job['jDateLastRun'], 0, 4) == '0000') {
		echo t('Never');
	} else {
		$runtime = date(DATE_APP_GENERIC_MDY . t(' \a\t ') . DATE_APP_GENERIC_TS, strtotime($job['jDateLastRun']) );
		echo $runtime;
	}
?></td>
	<td class="jLastStatusText"><?=t($job['jLastStatusText'])?></td>
	<td><?if(!$job['jNotUninstallable']):?>
		<?=$ih->button(t('Remove'), $this->action('uninstall', $job['jID']), null, 'remove')?>
	<?endif?></td>
</tr>
<?endforeach?>
</tbody>
</table>
<?endif?>

<?if($availableJobs):?>
<h2><?=t('Jobs Available for Installation')?></h2>
<table class="table table-striped">
<thead>
	<tr> 
		<th><?=t('Name')?></th>
		<th><?=t('Description')?></th> 
		<th></th>
	</tr>
</thead>
<tbody>
	<?foreach($availableJobs as $availableJobName => $job):?>
	<tr> 
		<td><?=$job->getJobName() ?></td>
		<td><?=$job->getJobDescription() ?></td> 
		<td><?if(!$job->invalid):?>
			<?=$ih->button(t('Install'), $this->action('install', $job->jHandle))?>
		<?endif?></td>
	</tr>	
	<?endforeach?>
</tbody>
</table>
<?endif?>
<div><?=t('If you wish to run these jobs in the background, automate access to the following URL:')?></div>
<div><a href="<?=BASE_URL.$this->url('/tools/required/jobs?auth='.$auth)?>"><?=BASE_URL . $this->url('/tools/required/jobs?auth=' . $auth)?></a></div>
</div>
<div class="ccm-pane-footer"><? if ($jobrunning == true) { ?>
	<form method="post" style="display: inline" action="<?=$this->action('reset_running_jobs')?>">
		<?=Loader::helper('validation/token')->output('reset_running_jobs')?>
		<input type="submit" class="btn" value="<?=t('Reset all Running Jobs')?>" />
	</form>
<? } ?></div>
<?=$h->getDashboardPaneFooterWrapper(false);?>

*/ ?>