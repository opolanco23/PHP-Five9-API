<?php
/**
 * This class uses five9 API to send records in various ways to a contact list
 * 
 * @link       https://github.com/opolanco23/PHP-Five9-API
 * @since      1.0.0
 *
 * @package    PHP Five9 API
 * @author     Orlando Polanco <me@orlandopolanco.us>
 */

Class addRecordsToFive9{

  /**
	 * The username set outside of any function so it can be set
	 * by any method required by the client code
   *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $username    The username used to connect to five9.
	 */
   private static $username = "";

   /**
 	 * The password set outside of any function so it can be set
 	 * by any method required by the client code
    *
 	 * @since    1.0.0
 	 * @access   private
 	 * @var      string    $password    The username used to connect to five9.
 	*/
  private static $password = "";

  /**
   * The array consists of keys that must match the name of fields inside your five9 contact field.
   * Each key is used to associate an array of settings to that field
   * supported data types are string, phone, int you may add as needed.
   * @since    1.0.0
   * @access   private
   * @var      array    $fields   (field name within five9) "key"  => array("type of data", min-length, max-length, is_key)
  */
  private static $fields = array(
            "first_name" =>  array( 'type' => "string"  , 'min' =>   3, 'max' => 100, 'is_key' => false ),
            "last_name"  =>  array( 'type' => "string"  , 'min' =>   3, 'max' => 100, 'is_key' => false ),
            "number1"    =>  array( 'type' => "phone"   , 'min' =>  10, 'max' =>  14, 'is_key' => true  ),
            "number2"    =>  array( 'type' => "phone"   , 'min' =>  10, 'max' =>  14, 'is_key' => false ),
            "state"      =>  array( 'type' => "string"  , 'min' =>   2, 'max' =>   2, 'is_key' => false ),
            "zip"        =>  array( 'type' => "int"     , 'min' =>   5, 'max' =>   5, 'is_key' => false ),
            "member_id"  =>  array( 'type' => "int"     , 'min' =>   5, 'max' =>  20, 'is_key' => false ),
  );


  /**
	 * Constructor is not used for any processing yet
   * but could be used to Initialize username and password.
   *
	 * @since    1.0.0
   * @return   null
	*/
  function __construct(){
    /* call to DB or external file to retrieve username and password
     * $credentials = getCredentials();
     * $this->$username = $credentials['username'];
     * $this->$password = $credentials['password'];
    */
  }

  /**
	 * Before Sending record to five9 the fields must be mapped with column number and the field name.
	 * Names of the fields must also match the names inside of the contact database.
   *
	 * @since    1.0.0
	 * @param    array         $record          the scrubbed array of lead data.
	 * @param    array         $mappedFields    An array of fields being mapped to five9 along with there properties
   * @param    integer       $index           where to start the column count. Some functions require 1 others 0
   * @return   array         $mappedFields    returns an array of arrays consisiting of the mappedFields
	*/
  protected function mapFields($record, $fields, $i){

    foreach ($record as $key => $value) {

      //map the field to the five9 system
      $mappedFields[] =  array( "columnNumber" => $i, "fieldName" => $key, "key" => $fields[$key]['is_key'] );

      $i++;
    }

    return $mappedFields;

  }


  /**
	 * Before Sending record to five9 the fields must be scrubbed to make sure the fields have the proper names.
	 * They also are scrubbed of any uncessary data decided by you or the system
   *
	 * @since    1.0.0
	 * @param    string        $fields           The array of field names and corresponding properties set for validation
	 * @param    boolean       $lead             the array of lead data that was sent from the client code.
   * @return   array         $data             the scrubed array of lead data.
	*/

  protected function scrubArray($fields, $lead){

    foreach($lead as $key => $value){

      //if the keys match and the field is the correct size
      if( array_key_exists($key, $fields) && ( strlen($value) >= $fields[$key]['min'] && strlen($value) <= $fields[$key]['max'] ) ):

        if($fields[$key]['type'] == 'string' && is_string($value)){

          $data[$key] = $value;
        }

        if($fields[$key]['type'] == 'phone' ){

          $data[$key] = preg_replace("/[^0-9]/", "", $value);

        }

        if($fields[$key]['type'] == 'int' && is_numeric($value)){

          $data[$key] = $value;
        }

      endif; //end keys match if

    }
    return $data;
  }

  //static function because authentication won't change
  protected static function authenticateMe(){

    // Import the WSDL and authenticate the user.-----------------------------
    $wsdl_five9 = "https://api.five9.com/wsadmin/v2/AdminWebService?wsdl&user=" . self::$username ;

    //try to authenticate with five9
    try{
        $soap_options = array( 'login'    =>  self::$username,
                               'password' =>  self::$password,
                               'trace' => true );

        $client_five9 = new SoapClient( $wsdl_five9 , $soap_options );

        $response['success'] = $client_five9;
    }//if errors occur add the message to the response array
    catch (Exception $e){

        $error_message = $e->getMessage();

        $response['error'] = $error_message;
    }

    return $response;

  }

  //send the record to five9
  function addRecordToList($lead, $list ){

    //the conctructed array
    $data = $this->scrubArray( self::$fields, $lead );

    //if the fields sent are all correct both arrays are the same size
    if(sizeof($lead) === sizeof($data) ){

      $client_five9 = self::authenticateMe();

      if( array_key_exists('success', $client_five9) ){

         //get the Soap Object
         $client = $client_five9['success'];

         //map the fields to five9 with the new ordered array
         $mappedFields = $this->mapFields($data, self::$fields, 1);


         //if the member_id is also included then send it to the memebers list
         if (array_key_exists("member_id" , $data)){
           $list = "members-oep";
         }


         //the mapped fields column number must match the index of the record
         //therefore we must make the associated array and indexed one
         $data = array_values($data);

          //settings required by five9
          $listUpdateSettings["fieldsMapping"] = $mappedFields;
          $listUpdateSettings["skipHeaderLine"] = false;
          $listUpdateSettings["cleanListBeforeUpdate"] = false;
          $listUpdateSettings["crmAddMode"] = 'ADD_NEW';
          $listUpdateSettings["crmUpdateMode"] = 'UPDATE_SOLE_MATCHES';
          $listUpdateSettings["listAddMode"] = 'ADD_IF_SOLE_CRM_MATCH';




          //prepare the query used to add the record to five9
          $query = array ( 'listName' => "$list",
                           'listUpdateSettings' => $listUpdateSettings,
                           'record' => $data );

         //try to add the record the five9 system
         try{
            //get the result from running the query
            //this will return an object
            $result = $client->AddRecordToList($query);

            //get the array of variables within the results object
            $resp = get_object_vars($result->return);

            //if there was an error adding the record
            if($resp['failureMessage'] != ""){
              $response['errors'] = $resp['failureMessage'];
            }

           //if it was successful either adding or updating
            if($resp['crmRecordsUpdated'] == 1 || $resp['crmRecordsInserted'] == 1){
              $response['success'] = true;
            }


          }//adding failed respond with error
          catch (Exception $e){
               //get the error message
               $error_message = $e->getMessage();
               //add the error message to the response array
               $response['error'] = $error_message;
          }

       }//end arraykey if

    }//end sizeof if
    else{
        //return the differences in the arrays usually caused due to improper names
        $response["errors"] = array_diff($lead, $data);

    }

    return $response;

  }//end function

  //more to come
  protected function addToListCsv(){

  }
  
  //more to come
  protected function createList(){

  }

}

?>
