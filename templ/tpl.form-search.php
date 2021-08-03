<?php if(!defined('Z_PROTECTED')) exit; ?>
		<form action="<?php eh($self); ?>" method="get">
			<input type="hidden" name="action" value="find" />
			Путь или файл: <input type="text" id="search" name="search" class="form-field" placeholder="mRemoteNG" value="<?php if(isset($_GET['search'])) eh($_GET['search']); ?>">
			<input type="submit" value="Найти" /><br />
		</form>

		<form action="<?php eh($self); ?>" method="get">
			<input type="hidden" name="action" value="findcomputer" />
			Имя ПК: <input type="text" id="searchpc" name="searchpc" class="form-field" placeholder="0000-W0000" value="<?php if(isset($_GET['searchpc'])) eh($_GET['searchpc']); ?>">
			<input type="submit" value="Найти" /><br />
		</form>
