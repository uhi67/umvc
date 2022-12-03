<?php
/** @var array $items */
/** @var array $options */

/**
	keys of items:
		- enabled: 	boolean, if item is clickable (default is true)
		- caption: 	text part of the item label
		- icon: 	icon class part of the item label (e.g. fa classnames)
		- title:	hint,
		- class:	item class
		- linkClass string|array, additional classnames for link tag, default is ['nav-link'], with 'badge-item' if badge is defined
		- url:		url to jump to
		- items:	array of subitems if menu is dropdown
 		- data:		array of data-* tag name=>value pairs
 		- badge:	badge caption (optional)
 */

use uhi67\umvc\Html;

?>
<ul <?= Html::attributes($options) ?>>
	<?php foreach($items as $item) {
		$itemClass = $item['class'] ?? []; if(is_string($itemClass)) $itemClass = explode(' ', $itemClass);
		$itemClass[] = 'nav-item';
		if(isset($item['items'])) $itemClass[] = 'dropdown';
		$itemOptions = $item['options'] ?? [];
		$icon = isset($item['icon']) ? Html::tag('i', '', ['class'=>$item['icon'].' me-1']) : '';
		$label = $icon . $item['caption'];
		$data = $item['data']??[];
		$linkClass = ['nav-link'];
		if($badge = $item['badge']??null) {
			$linkClass[] = 'badge-item';
			$data['badge-caption'] = $badge;
		}
		if(isset($item['items'])) $linkClass[] = 'dropdown-toggle';
		if(!($item['enabled'] ?? true)) $linkClass[] = 'disabled';
		if(isset($item['linkClass'])) {
			$linkClass = array_merge($linkClass,
				is_array($item['linkClass']) ? $item['linkClass'] : explode(' ', $item['linkClass']));
		}
		$linkOptions = ['class'=>implode(' ', $linkClass)];
		if(isset($item['items'])) $linkOptions = array_merge($linkOptions, ['data-bs-toggle'=>'dropdown']);
		foreach($data as $k=>$v) $linkOptions['data-'.$k] = $v;
		if(isset($item['url'])) $linkOptions['href'] = $item['url'];
		$itemContent = Html::tag('a', $label, $linkOptions);
		if(isset($item['items'])) {
			$subItems = '';
			foreach($item['items'] as $subItem) {
				$subEnabled = $subItem['enabled'] ?? true;
				$subLinkOptions = ['class'=>'dropdown-item' . ($subEnabled ? '' : ' disabled'), 'title'=>$subItem['title'] ?? ''];
				$subIcon = isset($subItem['icon']) ? Html::tag('i', '', ['class'=>$subItem['icon'].' me-1']) : '';
				if(isset($subItem['url'])) $subLinkOptions['href'] = $subItem['url'];
				$subItems .= Html::tag('a', $subIcon . $subItem['caption'], $subLinkOptions);
			}
			$itemContent .= Html::tag('div', $subItems, ['class'=>'dropdown-menu']);
		}
		$itemOptions = array_merge($itemOptions, ['class'=>implode(' ', $itemClass)]);
		$title = $item['title'] ?? '';
		if($title) $itemOptions['title'] = $title;
		echo Html::tag('li', $itemContent, $itemOptions);
	}
	?>
</ul>
