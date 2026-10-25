<header class="pb-m">
	<h1 class="corner-accent-error mb-m">
		Error <?= $exception->getCode() ?><br>
	</h1>
	<div class="legend"><?= $exception->getMessage() ?></div>
</header>
<div class="pb-m">
	<div><span  class="text-error">Class</span> : <?= get_class($exception) ?> </div>
	<div><span  class="text-error">File</span> : <?= $exception->getFile() . ':' . $exception->getLine() ?></div>
</div>

<h2 class="corner-accent-info" >Stack Trace</h2>
<div class="trace-list">
	<?php foreach ($exception->getTrace() as $value): ?>
		<?php $hasArgs = !empty($value['args']); ?>
		<<?= $hasArgs ? 'details' : 'div' ?> class="trace">
			<<?= $hasArgs ? 'summary' : 'div' ?> class="trace-summary">
				<span class="trace-file"><?= isset($value['file'])? $value['file'] : ''; ?></span>
				<span class="trace-line"><?= isset($value['line'])?': '.$value['line']:''; ?></span>
				<span class="trace-class"><?= isset($value['class'])?$value['class']:''; ?></span>
				<span class="trace-type"><?= isset($value['type'])?$value['type']:''; ?></span>
				<span class="trace-function"><?= isset($value['function'])?$value['function']:''; ?>(<?= $hasArgs?'$arg['.count($value['args']).']':'' ; ?>)</span>
			</<?= $hasArgs ? 'summary' : 'div' ?>>
			<?php if ($hasArgs): ?>
			<section class="trace-args">
				<?php debug($value['args'],' '); ?>
			</section>
			<?php endif ?>
		</<?= $hasArgs ? 'details' : 'div' ?>>
	<?php endforeach ?>
</div>
