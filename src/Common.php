<?php

namespace SqlClass;

abstract class Common {


	/**
	 * Parse the options array passed
	 * Basically just merge the two arrays, giving non-default options the preference
	 * Also ensures that each paramater in the options array is valid
	 */
	public function getOptions($options,$defaults) {

		$options = $this->toArray($options);

		foreach($options as $key => $val) {
			if(!array_key_exists($key,$defaults)) {
				throw new \Exception("Unknown parameter (" . $key . ")");
			}
			$defaults[$key] = $val;
		}

		return $defaults;

	}


	/**
	* Ensure the passed parameter is an array
	* If not, create an empty array
	*/
	public function toArray($array) {
		if(is_array($array)) {
			return $array;
		}
		return array();
	}


}
