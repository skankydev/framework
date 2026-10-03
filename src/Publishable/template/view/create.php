%?php
$this->setLayout('layout.default');
$this->addCrumb('<?= $name ?>', ['action' => 'index'], '');
$this->addCrumb('Ajouter', ['action' => 'create'], 'icon-add');
?>

<header class="page-header">
	<div class="page-title-wrapper">
		<h2 class="page-title">
			<i class="text-secondary "></i>
			Ajouter <?= $name ?>
		</h2>
	</div>
	<div class="page-action">
		<a href="%?= $this->url(['action'=>'index']) ?>" class="btn btn-secondary">
			<i class="icon-arrow-left"></i>
			Retour
		</a>
	</div>
</header>

<section class="page-content">
	<div class="card p-m">
		%?= $form->render() ?>
	</div>
</section>
