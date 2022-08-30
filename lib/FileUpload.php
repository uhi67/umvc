<?php
namespace uhi67\umvc;

/**
 * # FileUpload
 * Represents an uploaded file of a form
 * ($_FILES["importfile"]) ? $_FILES["importfile"]['tmp_name'] : false;
 *
 */
class FileUpload extends Component {
	/** @var string The temporary filename of the file in which the uploaded file was stored on the server. */
	public $tmp_name;
	/** @var string The original name of the file on the client machine. */
	public $name;
	public $type;
	public $size;
	public $error;
	public $fieldname;
	/** @var string $saved -- filename where this file has been saved or null if not yet saved  */
	public $saved;

	/**
	 * @param string $fieldname
	 * @param string $index -- fieldname if is in an array request variable
	 *
	 * @return FileUpload|null -- the object or null if not found
	 */
	public static function createFromField($fieldname, $index=null) {
		if(!isset($_FILES) || !isset($_FILES[$fieldname])) return null;
		if($index) {
			$filearray = $_FILES[$fieldname];
			return new FileUpload(array_map(function($value) use($index) { return $value[$index]; }, $filearray));
		}
		// Normal fieldname
		return new FileUpload($_FILES[$fieldname]);
	}

	/**
	 * Moves uploaded file to a final destination
	 * @param string $filename -- the new filename
	 *
	 * @return boolean -- success (false if file is saved already)
	 */
	public function save($filename) {
		if($this->saved) return false;
		$result = move_uploaded_file($this->tmp_name, $filename);
		if($result) $this->saved = $filename;
		return $result;
	}

	public function toString() {
		return '{file:'.$this->name.'}';
	}
}
