<?php

abstract class SqlClassCommon {


	/**
	 * Parse the options array passed
	 * Basically just merge the two arrays, giving user specified options the preference
	 * Also ensures that each paramater in the user array is valid
	 */
	public function getOptions($userSpecified,$defaults) {

		if(!is_array($userSpecified)) {
			return $defaults;
		}

		foreach($userSpecified as $key => $val) {
			if(!array_key_exists($key,$defaults)) {
				throw new Exception("Unknown parameter (" . $key . ")");
			}
			$defaults[$key] = $val;
		}

		return $defaults;

	}


}
