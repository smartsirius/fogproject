<?php
/** Class Name: FOGController
	Controller that extends FOGBase
	Gets the database information
	and returns it to be used as needed.
*/
abstract class FOGController extends FOGBase
{
	/** @var databaseTable
	  * Sets the databaseTable to perform lookups
	  */
	public $databaseTable = '';
	/** @var databaseFields
	  * The Fields the database contains
	  * using common for friendly names
	  */
	public $databaseFields = array();
	/** @var loadQueryTemplateMultiple
	  * The Query template in case of multiple items passed to data
	  * Protected so as to allow other classes to assign into them
	  */
	protected $loadQueryTemplateSingle = "SELECT * FROM `%s` %s WHERE `%s`='%s'";
	/** @var loadQueryTemplateMultiple
	  * The Query template in case of multiple items passed to data
	  */
	protected $loadQueryTemplateMultiple = "SELECT * FROM `%s` %s WHERE %s";
	/** @var databaseFieldsToIgnore
	  * Which fields to not really care about updatin
	  */
	public $databaseFieldsToIgnore = array(
		'createdBy',
		'createdTime'
	);
	/** @var additionalFields
	  * Fields to allow assignment into object
	  * but are not directly associated to the
	  * objects table
	  */
	public $additionalFields = array();
	/** @var aliasedField
	  * Aliased fields that aren't directly related to db
	  * but not capable of being updated or searched
	  */
	public $aliasedFields = array();
	/** @var databaseFieldsRequired
	  * Required fields to allow updating/inserting into
	  * the database
	  */
	public $databaseFieldsRequired = array();
	/** @var data
	  * The data to actually set and return to the object
	  */
	protected $data = array();
	/** @var autoSave
	  * If set, when the object is destroyed it will save first.
	  */
	public $autoSave = false;
	/** @var databaseFieldClassRelationships
	  * Set the classes to associate to between objects
	  * This is hard as most use associative properties
	  * But each object (including associations) are 
	  * counted as their own objects
	  */
	public $databaseFieldClassRelationships = array();
	/** The Manager of the info. */
	/** @var Manager
	  * Just sets the class manager field as needed.
	  */
	private $Manager;
	/** @param data
	  * Initializer of the objects themselves.
	  */
	public function __construct($data)
	{
		/** FOGBase constructor
		  * Allows the rest of the base of fog to come
		  * with the object begin called
		  */
		parent::__construct();
		try
		{
			/** sets if to print controller debug information to screen/log/either/both*/
			$this->debug = false;
			/** sets if to print controller general information to screen/log/either/both*/
			$this->info = false;
			// Error checking
			if (!count($this->databaseFields))
				throw new Exception('No database fields defined for this class!');
			// Flip database fields and common name - used multiple times
			$this->databaseFieldsFlipped = array_flip($this->databaseFields);
			// Created By
			if (array_key_exists('createdBy', $this->databaseFields) && !empty($_SESSION['FOG_USERNAME']))
				$this->set('createdBy', $this->DB->sanitize($_SESSION['FOG_USERNAME']));
			if (array_key_exists('createdTime', $this->databaseFields))
				$this->set('createdTime', $this->nice_date()->format('Y-m-d H:i:s'));
			// Add incoming data
			if (is_array($data))
			{
				foreach($data AS $key => $value)
					$this->set($this->key($key), $value);
			}
			// If incoming data is an INT -> Set as ID -> Load from database
			elseif (is_numeric($data))
			{
				if ($data === 0 || $data < 0)
					throw new Exception(sprintf('No data passed, or less than zero, Value: %s', $data));
				$this->set('id', $data)->load();
			}
		}
		catch (Exception $e)
		{
			$this->error('Record not found, Error: %s', array($e->getMessage()));
		}
		return $this;
	}
	// Destruct
	/** __destruct()
		At close of class, it trys to save the information if autoSave is enabled for that class.
	*/
	public function __destruct()
	{
		// Auto save
		if ($this->autoSave)
			$this->save();
	}
	// Set
	/** set($key, $value)
		Set's the fields relevent for that class.
	*/
	public function set($key, $value)
	{
		try
		{
			$this->info('Setting Key: %s, Value: %s',array($key,$value));
			if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !array_key_exists($key,$this->databaseFieldClassRelationships))
				throw new Exception('Invalid key being set');
			if (array_key_exists($key, $this->databaseFieldsFlipped))
				$key = $this->databaseFieldsFlipped[$key];
			$this->data[$key] = $value;
		}
		catch (Exception $e)
		{
			$this->debug('Set Failed: Key: %s, Value: %s, Error: %s', array($key, $value, $e->getMessage()));
		}
		return $this;
	}
	// Get
	/** get($key = '')
		Get's all fields or the specified field for the class member.
	*/
	public function get($key = '')
	{
		return ($key && isset($this->data[$key]) ? $this->data[$key] : (!$key ? $this->data : ''));
	}
	// Add
	/** add($key, $value)
		Used to add a new field to the database relevant to the class.
		Could potentially be used to add a new moderation field to the database??
	*/
	public function add($key, $value)
	{
		try
		{
			if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !array_key_exists($key,$this->databaseFieldClassRelationships))
				throw new Exception('Invalid data being added');
			$this->info('Adding Key: %s, Value: %s',array($key,$value));
			if (array_key_exists($key, $this->databaseFieldsFlipped))
				$key = $this->databaseFieldsFlipped[$key];
			$this->data[$key][] = $value;
		}
		catch (Exception $e)
		{
			$this->debug('Add Failed: Key: %s, Value: %s, Error: %s', array($key, $value, $e->getMessage()));
		}
		return $this;
	}
	// Remove
	/** remove($key, $object)
		Removes a field from the relevant class caller.
		Can be used to remove fields from the database??
	*/
	public function remove($key, $object)
	{
		try
		{
			if (!array_key_exists($key, $this->databaseFields) && !in_array($key, $this->additionalFields) && !array_key_exists($key, $this->databaseFieldsFlipped) && !array_key_exists($key,$this->databaseFieldClassRelationships))
				throw new Exception('Invalid data being removed');
			if (array_key_exists($key, $this->databaseFieldsFlipped))
				$key = $this->databaseFieldsFlipped[$key];
			foreach ((array)$this->data[$key] AS $i => $data)
			{
				if ($data instanceof MACAddress)
					$newDataArray[] = $data;
				else if ($data && $data->isValid && $data->get('id') != $object->get('id'))
					$newDataArray[] = $data;
			}
			$this->data[$key] = (array)$newDataArray;
		}
		catch (Exception $e)
		{
			$this->debug('Remove Failed: Key: %s, Object: %s, Error: %s', array($key, $object, $e->getMessage()));
		}
		return $this;
	}
	// Save
	/** save()
		Saves the information stored in the class variables to the database.
	*/
	public function save()
	{
		try
		{
			// Error checking
			if (!$this->isTableDefined())
				throw new Exception('No Table defined for this class');
			// Variables
			$fieldData = array();
			$this->array_remove($this->aliasedFields,$this->databaseFields);
			$this->array_remove($this->databaseFieldsToIgnore,$this->databaseFields);
			$fieldsToUpdate = $this->databaseFields;
			// Build insert key and value arrays
			foreach ($this->databaseFields AS $name => $fieldName)
			{
				if ($this->get($name) != '')
				{
					$insertKeys[] = $fieldName;
					$insertValues[] = $this->DB->sanitize($this->get($name));
				}
			}
			// Build update field array using filtered data
			foreach ($fieldsToUpdate AS $name => $fieldName)
				$updateData[] = sprintf("`%s` = '%s'", $this->DB->sanitize($fieldName), $this->DB->sanitize($this->get($name)));
			// Force ID to update so ID is returned on DUPLICATE UPDATE - No ID was returning when A) Nothing is inserted (already exists) or B) Nothing is updated (data has not changed)
			$updateData[] = sprintf("`%s` = LAST_INSERT_ID(%s)", $this->DB->sanitize($this->databaseFields['id']), $this->DB->sanitize($this->databaseFields['id']));
			// Insert & Update query all-in-one
			$query = sprintf("INSERT INTO `%s` (`%s`) VALUES ('%s') ON DUPLICATE KEY UPDATE %s",
				$this->databaseTable,
				implode("`, `", (array)$insertKeys),
				implode("', '", (array)$insertValues),
				implode(', ', $updateData)
			);
			if (!$this->DB->query($query))
				throw new Exception($this->DB->sqlerror());
			// Database query was successful - set ID if ID was not set
			if (!$this->get('id'))
				$this->set('id', $this->DB->insert_id());
			// Success
			return true;
		}
		catch (Exception $e)
		{
			$this->debug('Database Save Failed: ID: %s, Error: %s', array($this->get('id'), $e->getMessage()));
		}
		// Fail
		return false;
	}
	// Load
	/** load($field = 'id')
		Defaults the load from database as ID, but can be used to load
		whichever field you want.
	*/
	public function load($field = 'id')
	{
		try
		{
			// Error checking
			if (!$this->isTableDefined())
				throw new Exception('No Table defined for this class');
			if (!$this->get($field))
				throw new Exception(sprintf('Operation field not set: %s', strtoupper($field)));
			list($join,$where) = $this->buildQuery();
			// Build query
			if (is_array($this->get($field)))
			{
				// Multiple values
				foreach($this->get($field) AS $fieldValue)
					$fieldData[] = sprintf("`%s`='%s'", $this->databaseFields[$field], $fieldValue);
				$query = sprintf(
					$this->loadQueryTemplateMultiple,
					$this->databaseTable,
					$join,
					implode(' OR ', $fieldData)
				);
			}
			else
			{
				// Single value
				$query = sprintf(
					$this->loadQueryTemplateSingle,
					$this->databaseTable,
					$join,
					$this->databaseFields[$field],
					$this->get($field)
				);
			}
			// Did we find a row in the database?
			if (!$queryData = $this->DB->query($query)->fetch()->get())
				throw new Exception(($this->DB->sqlerror() ? $this->DB->sqlerror() : 'Row not found'));
			$this->setQuery($queryData);
			// Success
			return true;
		}
		catch (Exception $e)
		{
			$this->set('id', 0)->debug('Database Load Failed: ID: %s, Error: %s', array($this->get('id'), $e->getMessage()));
		}
		// Fail
		return false;
	}
	public function buildQuery($not = false,$compare = '=')
	{
		foreach((array)$this->databaseFieldClassRelationships AS $class => $fields)
		{
			$join[] = sprintf(' LEFT OUTER JOIN `%s` ON `%s`.`%s`=`%s`.`%s` ',$this->getClass($class)->databaseTable,$this->getClass($class)->databaseTable,$this->getClass($class)->databaseFields[$fields[0]],$this->databaseTable,$this->databaseFields[$fields[1]]);
			if ($fields[3])
			{
				foreach((array)$fields[3] AS $field => $value)
				{
					if (is_array($value))
						$whereArrayAnd[] = sprintf("`%s`.`%s` %s IN ('%s')",$this->getClass($class)->databaseTable,$this->getClass($class)->databaseFields[$field],($not ? 'NOT' : ''), implode("','",$value));
					else
						$whereArrayAnd[] = sprintf("`%s`.`%s` %s%s '%s'",$this->getClass($class)->databaseTable,$this->getClass($class)->databaseFields[$field],($not ? '!' : ''),(preg_match('#%#',$value) ? 'LIKE' : $compare),$value);
				}
			}
		}
		return array(implode((array)$join),$whereArrayAnd);
	}
	public function setQuery($queryData)
	{
		//	$classData = array_intersect_key($queryData,$this->databaseFieldsFlipped);
		foreach($queryData AS $key => $value)
			$this->set($this->key($key),(string)$value);
		foreach((array)$this->databaseFieldClassRelationships AS $class => $fields)
			$this->add($fields[2],$this->getClass($class)->setQuery($queryData));
		return $this;
	}
	// Destroy
	/** destroy($field = 'id')
		Can be used to delete items from the databased.
	*/
	public function destroy($field = 'id')
	{
		try
		{
			// Error checking
			if (!$this->isTableDefined())
				throw new Exception('No Table defined for this class');
			if (!$this->get($field))
				throw new Exception(sprintf('Operation field not set: %s', strtoupper($field)));
			// Query row data
			$query = sprintf("DELETE FROM `%s` WHERE `%s`='%s'",
				$this->DB->sanitize($this->databaseTable),
				$this->DB->sanitize($this->databaseFields[$field]),
				$this->DB->sanitize($this->get($field))
			);
			// Did we find a row in the database?
			if (!$queryData = $this->DB->query($query)->fetch()->get())
				throw new Exception('Failed to delete');
			// Success
			return true;
		}
		catch (Exception $e)
		{
			$this->debug('Database Destroy Failed: ID: %s, Error: %s', array($this->get('id'), $e->getMessage()));
		}
		// Fail
		return false;
	}
	// Key
	/** key($key)
		Checks if a relevant key exists within the database.
	*/
	public function key($key)
	{
		if (array_key_exists($key, $this->databaseFieldsFlipped))
			$key = $this->databaseFieldsFlipped[$key];
		return $key;
	}
	// isValid
	/** isValid()
		Checks that the returned items are valid for the relevant class calling it.
	*/
	public function isValid()
	{
		try
		{
			foreach ($this->databaseFieldsRequired AS $field)
			{
				if (!$this->get($field))
					throw new Exception($foglang['RequiredDB']);
			}
			if ($this->get('id') || $this->get('name'))
				return true;
		}
		catch (Exception $e)
		{
			$this->debug('isValid Failed: Error: %s', array($e->getMessage()));
		}
		return false;
	}
	/** getManager()
		Checks the relevant class manager class file (Image => ImageManager, Host => HostManager, etc...)
	*/
	public function getManager()
	{
		if (!is_object($this->Manager))
		{
			$managerClass = get_class($this) . 'Manager';
			$this->Manager = new $managerClass();
		}
		return $this->Manager;
	}
	
	// isTableDefined 
	/** istableDefined()
		Makes sur ethe table being called is defined in the database.  osID on hosts database table is not defined anymore.
		This would return false in that case.
	*/
	private function isTableDefined()
	{
		return (!empty($this->databaseTable) ? true : false);
	}
	// Name is returned if class is printed
	/** __toString()
		Returns the name of the class as a string.
	*/
	public function __toString()
	{
		return ($this->get('name') ? $this->get('name') : sprintf('%s #%s', get_class($this), $this->get('id')));
	}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
