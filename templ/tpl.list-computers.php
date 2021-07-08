<?php include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.header.php'); ?>
<?php include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.form-search.php'); ?>

		<?php if(!empty($path)) { ?>
			<h3 align="center">Список компьютеров, на которых существует выбранный файл</h3>

			<p>
				<b>Путь: </b><?php eh($path); ?><br />
				<b>Файл: </b><?php eh($filename); ?>
			</p>
		<?php } else { ?>
			<h3 align="center">Список компьютеров</h3>
		<?php } ?>

		<?php if(isset($result)) { ?>
			<table id="table" class="main-table">
				<thead>
					<tr>
						<th width="5%">ID</th>
						<th width="80%">Name</th>
						<th width="10%">Flags</th>
					</tr>
				</thead>
				<tbody id="table-data">
					<?php $i = 0; foreach($result as &$row) { $i++; ?>
						<tr>
							<td><a href="<?php eh('?action=computerinfo&id='.$row['id']); ?>"><?php eh($row['id']); ?></a></td>
							<td><?php eh($row['name']); ?></td>
							<td><?php eh(flags_to_string(intval($row['flags']), $g_comp_short_flags, '', '-')); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>

			<a class="page-number<?php if($offset == 0) eh(' boldtext'); ?>" href="<?php eh($self.'?action='.$action.'&id='.$id.'&offset=0&searchpc='.urlencode(@$_GET['searchpc'])); ?>">1</a>
			<?php 
				$min = max(100, $offset - 1000);
				$max = min($offset + 1000, $total - ($total % 100));

				if($min > 100) { echo '&nbsp;...&nbsp;'; }

				for($i = $min; $i <= $max; $i += 100)
				{
				?>
					<a class="page-number<?php if($offset == $i) eh(' boldtext'); ?>" href="<?php eh($self.'?action='.$action.'&id='.$id.'&offset='.$i.'&searchpc='.urlencode(@$_GET['searchpc'])); ?>"><?php eh($i/100 + 1); ?></a>
				<?php
				}

				$max = $total - ($total % 100);
				if($i < $max)
				{
				?>
					&nbsp;...&nbsp;<a class="page-number<?php if($offset == $max) eh(' boldtext'); ?>" href="<?php eh($self.'?action='.$action.'&id='.$id.'&offset='.$max.'&searchpc='.urlencode(@$_GET['searchpc'])); ?>"><?php eh($max/100 + 1); ?></a>
				<?php
				}
			?>

		<?php } ?>

		<br />
		<b>Описание флагов:</b>
		<pre><?php eh(flags_to_legend($g_comp_short_flags, $g_comp_flags, "\n")); ?></pre>
		<br />

<?php include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.footer.php'); ?>
