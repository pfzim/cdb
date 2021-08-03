<?php include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.header.php'); ?>
<?php include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.form-search.php'); ?>

		<h3 align="center">Список файлов на компьютере <?php eh($computer); ?></h3>

		<?php if(isset($result)) { ?>
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
							<td><a href="<?php eh('?action=pathinfo&id='.$row['id']); ?>"><?php eh($row['id']); ?></a></td>
							<td><?php eh($row['path']); ?></td>
							<td><?php eh($row['filename']); ?></td>
							<td><?php eh(flags_to_string(intval($row['flags']), $g_files_short_flags, '', '-')); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		<?php } ?>

		<br />
		<b>Описание флагов:</b>
		<pre><?php eh(flags_to_legend($g_files_short_flags, $g_files_flags, "\n")); ?></pre>
		<br />

<?php include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.footer.php'); ?>
