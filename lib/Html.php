<?php /** @noinspection HtmlUnknownAttribute */

namespace uhi67\umvc;

/**
 * Html builder helper
 * @package UMVC Simple Application Framework
 */
class Html {
    /**
     * Returns whether a value starts with "http://" or "https://".
     *
     * @param string|NULL $val The value to check.
     * @return boolean
     */
    public static function isAbsoluteUrl($val) {
        return strpos($val, 'http://') === 0 || strpos($val, 'https://') === 0;
    }

    /**
     * Returns the HTML string of an `<tag>` element
     *
     * @param $tag -- cleaned
     * @param $content -- not cleaned to allow embed HTML structures
     * @param $options -- cleaned. Options without value (null or false) will be omitted. Boolean `true` results an attribute present without value.
     * @return string
     */
    public static function tag($tag, $content, $options=[]) {
        $tag = AppHelper::toNameID($tag);
        $parts = [$tag];
        if($options) foreach($options as $attr=>$value) {
            $name = AppHelper::toNameID($attr);
            if($value===true) $parts[] = $name;
            if($value!==null && $value!==false) {
                if(!is_scalar($value)) throw new Exception('Attribute value must be scalar');
                $parts[] = $name.'="'.AppHelper::xss_clean($value).'"';
            }
        }
        return '<'.implode(' ', $parts).'>'.$content.'</'.$tag.'>';
    }

    /**
     * Returns the HTML string of an `<a>` element (possibly including the "mailto" URI schema) or an error string.
     * You should use Html::tag() instead when the URL is relative.
     *
     * @param string|NULL $href The value to return HTML from if an absolute URL (see {@see isAbsoluteUrl()}) or email address.
     * @param string|NULL $content The visual text to show if any, otherwise the input href is used. Cleaned!
     * @return string
     */
    public static function a($href, $content=null, $options=[]) {
        $href = AppHelper::xss_clean($href);

        // determine whether the input value is a URL (possibly containing @) or email alike
        $hrefIsUrl = self::isAbsoluteUrl($href);
        $hrefIsEmail = !$hrefIsUrl && preg_match('/.+@.+/', $href);

        // set content
        if(!isset($content) || trim($content)==='') {
            $content = $href;
        }
        $content = AppHelper::xss_clean($content);

        if($hrefIsUrl) {
            $options['href'] = $href;
        }
        elseif($hrefIsEmail) {
            $options['href'] = 'mailto:'.$href;
        }
        else {
            $options['class'] = 'error';
            return static::tag('span', $content, $options);
        }
        return static::tag('a', $content, $options);
    }

    /**
     * Returns the HTML string of a button element with optional id attribute.
     *
     * @param string $text text to display
     * @param string $class optional additional class for the button
     * @param string $id optional ID for the button
     * @return string
     */
    public static function button($text, $class=NULL, $id=NULL) {
        return sprintf('<button type="button" class="btn btn-secondary %s" %s>%s</button>',
            $class ?? '', isset($id) ? 'id="'.$id.'"' : '', $text);
    }

    /**
     * Returns the HTML string of a button-like `<a>` element or a disabled `<button>`.
     * Please note that `<a>` elements can be disabled as well, but this is easily achieved with `<button>` elements.
     *
     * @param string|NULL $href The href to return a link button from.
     * @param string|NULL $enabledText The text to use if the input href is a URL.
     * @param string|NULL $disabledText The text to use if the input href is not a URL.
     * @return string
     */
    public static function linkButton($href, $enabledText, $disabledText) {
        $href = AppHelper::xss_clean($href);

        return self::isAbsoluteUrl($href) ?
               sprintf('<a role="button" class="btn btn-secondary btn-sm" target="_blank" href="%s">%s</a>', $href, $enabledText) :
               sprintf('<button type="button" class="btn btn-secondary btn-sm" disabled>%s</button>', $disabledText)
        ;
    }

    /**
     * Render a `select` HTML tag with options
     *
     * @param array $values -- the selection
     * @param array $options -- html options of select tag
     * @param string|null $actualValue -- the actual selection
     * @return string -- HTML rendered tag
     */
    public static function select(array $values, $options=[], $actualValue=null) {
        return Html::tag('select', implode("\n", array_map(function($value, $label) use($actualValue) {
            $opt = ['value'=>$value];
            if($actualValue!==null && $actualValue !=='' && $actualValue==$value) $opt['selected']=true;
            return Html::tag('option', $label, $opt);
        }, array_keys($values), array_values($values))), $options);
    }

    public static function link($options=[]) {
        return Html::tag('link', '', $options);
    }
}
