<?php
	/** @var \uhi67\umvc\App $this */
	/** @var array $messages -- [[severity, message],...] */

	$content = '';
	$classes = [
		'info' => 'alert-info',
		'success' => 'alert-success',
		'warning' => 'alert-warning',
		'failure' => 'alert-danger',
		'error' => 'alert-danger',
	];
	$titles = [
		'info' => '',
		'success' => 'Success!',
		'warning' => 'Warning!',
		'failure' => 'Oops!',
		'error' => 'Oops!',
	];
	foreach($messages as $flash_message) {
		$severity = 'info';
		if(is_array($flash_message)) {
			$severity = $flash_message[0];
			$flash_message = $flash_message[1];
		}
		$class = $classes[$severity] ?? 'alert-secondary';
		$title = $titles[$severity] ?? '';
		$content .= $this->renderPartial('widgets/_flash', [
			'class' => $class,
			'text1' => $title,
			'text2' => $flash_message
		]);
	}
	echo $content;
