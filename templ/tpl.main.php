<?php include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.header.php'); ?>
<?php include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.form-search.php'); ?>

		<?php if(isset($result)) { ?>
		<h3 align="center">Найденные файлы и папки в БД</h3>

			<table id="table" class="main-table">
				<thead>
					<tr>
						<th width="5%">ID</th>
						<th width="60%">Path</th>
						<th width="25%">File</th>
						<th width="10%">Flags</th>
					</tr>
				</thead>
				<tbody id="table-data">
					<?php $i = 0; foreach($result as &$row) { $i++; ?>
						<tr>
							<td><a href="<?php eh($self.'?action=pathinfo&id='.$row['id']); ?>"><?php eh($row['id']); ?></a></td>
							<td><?php eh($row['path']); ?></td>
							<td><?php eh($row['filename']); ?></td>
							<td><?php eh(flags_to_string(intval($row['flags']), $g_files_inventory_flags, '', '-')); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>

			<a class="page-number<?php if($offset == 0) eh(' boldtext'); ?>" href="<?php eh($self.'?action=find&offset=0&search='.urlencode(@$_GET['search'])); ?>">1</a>
			<?php 
				$min = max(100, $offset - 1000);
				$max = min($offset + 1000, $total - ($total % 100));
				
				if($min > 100) { echo '&nbsp;...&nbsp;'; }
				
				for($i = $min; $i <= $max; $i += 100)
				{
				?>
					<a class="page-number<?php if($offset == $i) eh(' boldtext'); ?>" href="<?php eh($self.'?action=find&offset='.$i.'&search='.urlencode(@$_GET['search'])); ?>"><?php eh($i/100 + 1); ?></a>
				<?php
				}

				$max = $total - ($total % 100);
				if($i < $max)
				{
				?>
					&nbsp;...&nbsp;<a class="page-number<?php if($offset == $max) eh(' boldtext'); ?>" href="<?php eh($self.'?action=find&offset='.$max.'&search='.urlencode(@$_GET['search'])); ?>"><?php eh($max/100 + 1); ?></a>
				<?php
				}
			?>

			<br />
			<b>Описание флагов:</b>
			<pre><?php eh(flags_to_legend($g_files_inventory_short_flags, $g_files_inventory_flags, "\n")); ?></pre>
		<?php } ?>

		<br />

<?php include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.footer.php'); ?>
