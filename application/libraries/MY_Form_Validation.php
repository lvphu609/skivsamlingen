<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class MY_Form_Validation extends CI_Form_Validation {

	function __construct()
	{
		parent::__construct();
	}

    function set_error($field, $message)
    {
        $this->_field_data[$field]['error'] = $message;

        if ( ! isset($this->_error_array[$field]))
        {
            $this->_error_array[$field] = $message;
        }
    }

    /**
     * Create a new unique nonce, save it to the current session and return it.
     *
     * @return string
     */
    function create_nonce()
    {
        $nonce = md5('nonce' . $this->CI->input->ip_address() . microtime());
        $this->CI->session->set_userdata('nonce', $nonce);
        return $nonce;
    }

    /**
     * Mark the nonce sent from the form as already used.
     */
    function save_nonce()
    {
        $this->CI->session->set_userdata('old_nonce', $this->set_value('nonce'));
    }

    /**
     * Set form validation rules for the nonce.
     */
    function nonce()
    {
        $this->set_rules('nonce', 'Nonce', 'required|check_nonce');
    }
	
	// --------------------------------------------------------------------
	
	/**
	 * Alpha-numeric with underscores, dashes and dots
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */	
	function alpha_dash_dot($str)
	{
		return ( ! preg_match("/^([-a-z0-9\._-])+$/i", $str)) ? FALSE : TRUE;
	}

	/**
	 * Validate date according to the specified format.
	 *
	 * @access	public
	 * @param	string
     * @param	date format
	 * @return	bool
	 */
	function valid_date($str, $format)
	{
        $date = date_parse_from_format($format, $str);
        return checkdate($date['month'], $date['day'], $date['year']);
	}


	/**
	 * Check that a value does not exist in the database table.
     * Param should be on the form Table.field (e.g. Users.username)
     *
     * http://net.tutsplus.com/tutorials/php/6-codeigniter-hacks-for-the-masters/
	 *
	 * @access	public
	 * @param	string
     * @param	table and field
	 * @return	bool
	 */
	function unique($value, $params) {
		$CI =& get_instance();
        $CI->load->database();

		list($table, $field) = explode(".", $params, 2);

		$query = $CI->db->select($field)->from($table)
			->where($field, $value)->limit(1)->get();

		if ($query->row()) {
			return false;
		} else {
			return true;
		}

	}

	/**
	 * Value must be numeric and less than or equal to the given parameter.
	 *
	 * @access	public
	 * @param	value
     * @param	max value
	 * @return	bool
	 */
	function numeric_max($str, $max)
	{
        if($this->numeric($str) && $this->numeric($max))
            return $str <= $max;
        else
            return false;
	}

	/**
	 * Value must be numeric and higher than or equal to the given parameter.
	 *
	 * @access	public
	 * @param	value
     * @param	min value
	 * @return	bool
	 */
	function numeric_min($str, $min)
	{
        if($this->numeric($str) && $this->numeric($min))
            return $str <= $min;
        else
            return false;
	}

 	/**
	 * Value must be in the comma separated set of values.
	 *
	 * @access	public
	 * @param	string
     * @param	set of valid values
	 * @return	bool
	 */
	function in_list($str, $list)
	{
        $set = explode(',', $list);
        return in_array($str, $set);
	}


 	/**
	 * Equal to a value.
	 *
	 * @access	public
	 * @param	string
     * @param	set of valid values
	 * @return	bool
	 */
	function equals($str, $comp)
	{
        return ($str == $comp);
	}

 	/**
	 * Make sure the nonce is valid.
	 *
	 * @access	public
	 * @param	string
     * @param	last used nonce
	 * @return	bool
	 */
	function check_nonce($str)
	{
        return ($str == $this->CI->session->userdata('nonce') &&
                $str != $this->CI->session->userdata('old_nonce'));
	}
	
}

/**
 * GET form, create and save nonce
 * POST form
 *      IF valid form
 *          save nonce as old_nonce
 *      ELSE
 *
 *
 *
 *
 */