%?php
$this->setLayout('layout.default');
$this->addCrumb('<?= $name ?>', ['action' => 'index'], '');
?>

<header class="page-header">
	<div class="page-title-wrapper">
		<h2 class="page-title">
			<i class="text-secondary "></i>
			<?= $name ?>
		</h2>
	</div>
	<div class="page-action">
		<a href="%?= $this->url(['action'=>'create']) ?>" class="btn btn-primary">
			<i class="icon icon-add"></i>
			Ajouter
		</a>
	</div>
</header>

<section class="page-content">
	<div class="card p-m">
		%?= $this->part('part.table', ['paginator' => $<?= $pluralCamel ?>]); ?>
	</div>
</section>
