<?php $aClass = 'btn-mini'; ?>
<?php $get = array_merge($sortGet ?? [], $get); // les liens de page conservent le tri courant ?>
<nav id="Paginator" class="text-center p-m">
	
	<?php $class = $aClass; if($first==$prev) $class .= ' btn-disabled'; ?>
	
		<?php 
			$get['page'] = $first;
			echo $this->link('&#60;&#60;',[...$link,'get'=>$get],['class'=>$class]);
		?>
	
	<?php $class = $aClass; if($page==$prev) $class .= ' btn-disabled'; ?>
	
		<?php
		$get['page'] = $prev;
		echo $this->link('&#60;',[...$link,'get'=>$get],['class'=>$class]); ?>
	
	<?php for ($i=$start; $i < $stop; ++$i): ?> 
		<?php $class = $aClass; if($i==$page) $class .= ' btn-success'; ?>
		
		<?php 
		$get['page'] = $i;
		echo $this->link($i,[...$link,'get'=>$get],['class'=>$class]); ?>
		
	<?php endfor ?>
	<?php $class = $aClass; if($page==$next) $class .= ' btn-disabled'; ?>
	
	<?php 
	$get['page'] = $next;
	echo $this->link('&#62;',[...$link,'get'=>$get],['class'=>$class]);
	?>
	
	<?php $class = $aClass; if($last==$next) $class .= ' btn-disabled'; ?>	
	
	<?php
	$get['page'] = $last;
	echo $this->link('&#62;&#62;',[...$link,'get'=>$get],['class'=>$class]); ?>
</nav>
