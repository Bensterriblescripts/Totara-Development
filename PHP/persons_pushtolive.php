<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mitowebservices\handlers;

/*
use auth_oauth2\linked_login;
use auth_oauth2\api;
use core\oauth2\issuer;
*/
use core_text;
use stdClass;
use core_user;
use core\session\manager;
use totara_core\event\user_suspended;
use \core\event\user_created;

/**
 * Class persons
 *
 * @package     local_mitowebservices\handlers
 * @copyright   2016, LearningWorks <admin@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class persons extends handler {
    /**
     * Moodle values for users suspended, not suspended.
     */
    const USER_NOT_SUSPENDED = 0;
    const USER_SUSPENDED = 1;

    /**
     * ITOMIC values for users suspended, not suspended.
     */
    const USER_STATUS_ACTIVE = 'Active';
    const USER_STATUS_INACTIVE = 'Inactive';

    /**
     * @var issuer $oauth2issuer
     
    private $oauth2issuer = null;
    */

    /**
     * persons constructor.
     *
     * @param array $persons    An array of webservice response objects containing ITOMIC user information.
     */
    public function __construct($persons = array()) {
        // The required library files for this. We need the user lib for updating users.
        $requiredlibraries = array('user/lib.php' => 'dirroot');

        // What fields are required in the ITOMIC webservice response object?
        $requiredfields = array(
            'id', 'username', 'firstname', 'lastname', 'email', 'mobile', 'landline', 'organisation', 'modified', 'trainingsupervisorid', 'totaraassessorgroup'
        );
        
        // Construct this object.
        parent::__construct($persons, $requiredlibraries, $requiredfields);        
    }

    /**
     * Process all person objects returned from the ITOMIC webservice.
     *
     * @return bool
     */
    public function process($justenablemessaging = false) {
        global $DB;

        // The parent process function checks for empty webservice responses.
        parent::process();


        // There are objects to process.
        foreach ($this->webserviceresponseobjects as $person) {
            // If the person object isn't a stdClass then we have serious problems! This should never happen, but it could.
            if (!$person instanceof stdClass) {
                // What type of object is the person object we received?
                $objecttype = gettype($person);

                // Get the error message for this.
                $errormessage = $this->get_string('process', "Expected person object to be stdClass. Got {$objecttype} instead");
                
                // Output the error message.
                mtrace($errormessage);

                // Todo: This needs to be logged somewhere and someone alerted perhaps.
                continue;
            }

            // Convert the user's username to a lowercase.
            $person->username = core_text::strtolower($person->username);

            // Check for username being UPN first.
            $hasupn = false;
            if (isset($person->userprincipalname) && !empty($person->userprincipalname)) {
                $hasupn = true;
                $person->username = $person->userprincipalname;
            }

            // Force OIDC
            $person->auth = 'oidc';

            // The conditions for users existing by idnumber, username and email.
            $conditionsidnumberusernameandemail = array(
                'idnumber' => $person->id, 'username' => $person->username, 'email' => $person->email, 'deleted' => 0
            );
            
            // Does the user exist by idnumber, username and email?
            $userexistsbyidnumberusernameandemail = $DB->record_exists('user', $conditionsidnumberusernameandemail);

            // The conditions for users existing by username and email.
            $conditionsusernameandemail = array('username' => $person->username, 'email' => $person->email, 'deleted' => 0);

            // Does the user exist by username and email?
            $userexistsbyusernameandemail = $DB->record_exists('user', $conditionsusernameandemail);

            // The conditions for users existing by idnumber.
            $conditionsidnumber = array('idnumber' => $person->id, 'deleted' => 0);

            // Does the user exist by idnumber?
            $userexistsbyidnumber = $DB->record_exists('user', $conditionsidnumber);

            // Set the users status.
            if (!isset($person->status)) {
                // Set this value if not implemented yet. Default value is active.
                $person->status = self::USER_STATUS_ACTIVE;
            }

            if (empty($person->status)) {
                // Assume an active person if there is no value.
                $person->status = self::USER_STATUS_ACTIVE;
            }

            // Translate the person status to a moodle status aka suspended 0 or 1.
            if (core_text::strtolower($person->status) == core_text::strtolower(self::USER_STATUS_ACTIVE)) {
                // 0 means not suspended.
                $person->status = self::USER_NOT_SUSPENDED;
            } else if (core_text::strtolower($person->status) == core_text::strtolower(self::USER_STATUS_INACTIVE)) {
                // 1 means is suspended.
                $person->status = self::USER_SUSPENDED;
            } else {
                $message = $this->get_string(
                    'process',
                    "Unknown status {$person->status} for user with idnumber {$person->id}, username {$person->username} and email {$person->email}. Assuming not suspended."
                );

                mtrace($message);

                $person->status = self::USER_NOT_SUSPENDED;
            }

            $existinguser = false;

            // If the user exists by idnumber, username and email then we need to update them.
            if ($userexistsbyidnumberusernameandemail) {
                // Prepare a message for the log.
                $message = $this->get_string(
                    'process',
                    "Updating user with the id {$person->id}, username {$person->username} and email {$person->email}.".
                    "Updating their details."
                );

                $existinguser = true;

                // Output the message to the log.
                mtrace($message);

                // Actual perform the update.
                $this->update_person($person, $conditionsidnumber);
            } else if ($userexistsbyusernameandemail) {
                // If the user exists by username and email then we need to update them. They should have an idnumber.

                // Prepare a message for the log.
                $message = $this->get_string(
                    'process', "Updating user with the id, {$person->username} and email {$person->email}. Updating their details."
                );

                $existinguser = true;

                // Output the message to the log.
                mtrace($message);

                // Actual perform the update.
                $this->update_person($person, $conditionsusernameandemail);
            } else if ($userexistsbyidnumber) {
                // If the user exists just by idnumber then they could be missing some stuff but I highly doubt it.

                // Prepare a message for the log
                $message = $this->get_string('process', "Updating user with the idnumber {$person->id}");

                // Output the message to the log.
                mtrace($message);

                $existinguser = true;

                // Actual perform the update.
                $this->update_person($person, $conditionsidnumber);
            } else {
                // The user doesn't exist. Still look for users that exist with the same email.
                $this->create_person($person);
            }

            if (!$hasupn) {
                continue;
            }

            $existinguser = false;
        }

        // Finished processing all users from ITOMIC webservice.
        return true;
    }

    /**
     * This is the first part of the ITOMIC to moodle user creation process.
     *
     * @param   stdClass $person   The ITOMIC webservice person object to create a moodle user from.
     * @return  bool
     */
    private function create_person(stdClass $person) {
        global $DB;

        // Does this person object have the required fields?
        if (!$this->has_required_fields($person)) {
            // Get the error message for this.
            $errormessage = $this->get_string(
                'create_person', "Person with id {$person->id} doesn't have all required fields. Can not process this person."
            );

            // Output the error message.
            mtrace($errormessage);

            // Todo: This needs to be logged somewhere and someone alerted perhaps.
            return false;
        }

        // Does this person object have a value set for username?
        if (!$person->username) {
            // Get the error message for this.
            $errormessage = $this->get_string(
                'process', "Person with id {$person->id} doesn't have a username. Can not process this person."
            );

            // Output the error message.
            mtrace($errormessage);

            $this->email_supportuser('MITO LMS failed to create user', $errormessage);

            // Todo: This needs to be logged somewhere and someone alerted perhaps.
            return false;
        }

        // Ensure the person username is not lowercase then we can't proceed.
        $person->username = core_text::strtolower($person->username);

        // Check the username for invalid characters.
        if ($person->username !== clean_param($person->username, PARAM_USERNAME)) {
            // Get the error message for this.
            $errormessage = $this->get_string(
                'process',
                "The username for person with id {$person->id} contains invalid characters ({$person->username}).".
                " Can not process this person."
            );

            // Output the error message.
            mtrace($errormessage);

            // Todo: This needs to be logged somewhere and someone alerted perhaps.
            $this->email_supportuser('MITO LMS failed to create user', $errormessage);
            return false;
        }

        // Check that the person has an email.
        if (!$person->email) {
            // Get the error message for this.
            $errormessage = $this->get_string(
                'process', "The person with id {$person->id} doesn't have an email address. Can not process this person"
            );

            // Output the error message.
            mtrace($errormessage);

            // Todo: This needs to be logged somewhere and someone alerted perhaps.
            $this->email_supportuser('MITO LMS failed to create user', $errormessage);
            return false;
        }

        // If the user exists by email already we can't continue.
        if ($existinguser = $DB->get_record('user', array( 'email' => $person->email ))) {
            // Generate an error message.
            $errormessage = $this->get_string(
                'process',
                "Can't add the person with id {$person->id} using email address {$person->email}!".
                " An existing user is already using that email address."
            );

            // Output the error message.
            mtrace($errormessage);

            $this->email_supportuser('MITO LMS failed to create user', $errormessage);

            return false;
        }

        // Convert the webservice responses timemodified field to a UNIX timestamp.
        $person->modified = $this->convert_timemodified($person->modified);

        // Get a stdClass object that represents the user we are going to add.
        $itomicperson = $this->construct_person($person);

        // Now that we have a person object we need to check that the values provided will fit in the target table.
        if (!$this->fields_below_max_length('user', $itomicperson)) {
            // Todo: This needs to be logged somewhere and someone alerted perhaps.
            return false;
        }
        
        // Validation passed. Lets actually create this person. A message is output in $this->add_person() on success.
        $this->add_person($itomicperson, $person);
    }

    /**
     * This is the second part of the ITOMIC to moodle user creation process. This is where we actually create the user and
     * do all of its user creation stuff and openid auth plugin things specifically associating this user to its openid url
     * as per the configuration specified in this plugins setting.
     *
     * All error checking should have been done before calling this function.
     *
     * @param   stdClass $itomicperson     The ITOMIC person object that was prepared earlier.
     * @param   stdClass $person           The ITOMIC webservice person object. We need this to get the openiduserfield from.
     * @throws  \exception                  Throws an exception when the function is called incorrectly.
     * @return  bool
     */
    private function add_person(stdClass $itomicperson, stdClass $person) {
        global $DB;

        // Get the field to append the users openid url to. Don't worry, this configuration is validated in the parent::process()
        // so please ensure that the parent::process() has been called first.
        $openidurluserfield = get_config('local_mitowebservices', 'openid_url_userfield');

        $authmethod = 'manual';

        // Create a password for the new user.
        if (isset($person->auth)) {
            $authmethod = $person->auth;
        }
		$authmethod = 'oidc';
		
        $password = hash_internal_user_password($authmethod);

        // Create the user in moodle with the auth method as openid.
        $moodleuser = create_user_record($itomicperson->username, $password, $authmethod);

        // Assign the person object the id of the newly created moodle user.
        $itomicperson->id = $moodleuser->id;

        if ($authmethod === 'openid' && function_exists('openid_append_url')) {
            // Add this user record to the openid_urls table using the openidperson object.
            openid_append_url($itomicperson, $this->openidurl.$person->$openidurluserfield);
        }

        $DB->update_record('user', $itomicperson);
        $itomicperson = get_complete_user_data('id', $itomicperson->id);

        // Trigger the user created event.
        user_created::create_from_userid($itomicperson->id)->trigger();

        // Map this user locally.
        $this->map_object('user', $itomicperson->id, $person->id, false, true);

        // Lets notify the output that the user was created.
        mtrace(
            $this->get_string('add_person', "Person with id {$person->id} was created sucessfully.")
        );

        // Check for user suspension and suspend if necessary.
        if (isset($itomicperson->suspended) && $itomicperson->suspended) {
            manager::kill_user_sessions($itomicperson->id);


            // Trigger a user suspended event.
            user_suspended::create_from_user($itomicperson)->trigger();
        }

        // Get out of here :).
        return true;
    }

    /**
     * This function is called when the ITOMIC webservice person object exists as a moodle user. We know that some of the persons
     * information has been changed or updated. This function will check for any differences between moodle's record of the user
     * and the ITOMIC webservice person object and update accordingly.
     *
     * By calling this function we expect that the person exists in moodle by both idnumber and username.
     *
     * @param   stdClass $person   The ITOMIC webservice person object to compare against moodle's record of the associated user.
     * @throws  \exception          Throws an exception when the function is called incorrectly.
     * @return  bool                True if the person was updated or false if no changes. This shouldn't really return false unless
     *                              the caller is false :joy:.
     */
    private function update_person(stdClass $person, $conditions = array()) {
        global $DB;
        global $persons;

        // Check the conditions array. This should have something i.e. key = field and value = the value to find.
        if (empty($conditions)) {
            // Lets notifiy the output that there was an issue. This shouldn't ever happen.
            mtrace($this->get_string('update_person', "Conditions can not be empty.", 'exception'));
            return false;
        }

        // Ensure that we are filtering for only users that are not deleted.
        if (!isset($conditions['deleted'])) {
            $conditions['deleted'] = 0;
        }

        // Find the moodle user for this person.
        $moodleuser = $DB->get_record('user', $conditions);

        // Find the authentication for this person.
        $conditions['auth'] = $person->auth;
        
        if (!$moodleuser) {
            // The person we are trying to update doesn't exist in moodle. This should never happen but we will catch it anyway.
            $errormessage = $this->get_string(
                'update_person', "The person with id {$person->id} you are trying to update doesn't exist in moodle."
            );

            // Output the error message.
            mtrace($errormessage);

            // Todo: This needs to be logged somewhere and someone alerted perhaps?
            return false;
        }

        // The user exists in moodle so lets look for any changes and update where necessary.
        $haschanges = false;

        // Has the username been updated? We initially think that it hasn't.
        $usernamehaschanged = false;

        //Change auth to string
        $person->auth = core_text::strtolower($person->auth);

        //Grab moodle userid for use in user_info_data
        $mdluser = $DB->get_record('user', ['idnumber' => $person->id]);

        //Create empty 'changes' booleans for later
        $assessorgroupchanges = false;
        $newassessorgrouprecord = false;

        //Determine assessor field changes
        if ($DB->record_exists('user_info_data', ['userid' => $mdluser->id, 'fieldid' => '6']) && !empty($person->totaraassessorgroup)) {
            $assessorgroupcurrent = $DB->get_record('user_info_data', ['userid' => $mdluser->id, 'fieldid' => '6']);
            if ($person->totaraassessorgroup != $assessorgroupcurrent->data) {
                $assessorgroupchanges = true;
            }
            $haschanges = true;
        }
        else if (!$DB->record_exists('user_info_data', ['userid' => $mdluser->id, 'fieldid' => '6']) && !empty($person->totaraassessorgroup)){
            $newassessorgrouprecord = true;
            $haschanges = true;
        }

        //Map the fields to the webservice response
        $fieldmappings = array(
            'username'      => 'username',
            'firstname'     => 'firstname',
            'lastname'      => 'lastname',
            'idnumber'      => 'id',
            'department'    => 'organisation',
            'timemodified'  => 'modified',
            'email'         => 'email',
            'phone1'        => 'mobile',
            'phone2'        => 'landline',
            'suspended'     => 'status',
            'auth'          => 'auth'
        );

        // Iterate over each of the fields that we need to update and check for any changes.
        foreach ($fieldmappings as $moodlefield => $itomicfield) {
            // Update the moodle user data if what is stored doesn't match the webservices data.
            if (!($person->$itomicfield == $moodleuser->$moodlefield)) {

                // We don't worry about time modified. This is different for ITOMIC.
                if ($moodlefield == 'timemodified') {
                    continue;
                }

                // Update the local_mitowebservices_user mapping because of idnumber change.
                if ($moodlefield == 'idnumber') {
                    $existingrecord = $DB->get_record('local_mitowebservices_user', ['sourcedid' => $moodleuser->idnumber]);

                    if ($existingrecord) {
                        $existingrecord->sourcedid = $person->id;

                        $DB->update_record('local_mitowebservices_user', $existingrecord);
                    }
                }
                
                // If there has been any changes with the username then we will need to update the openid_urls table.
                if ($moodlefield == 'username') {
                    $usernamehaschanged = true;
                }
                
                // The field to update is not the timemodified field.
                $moodleuser->$moodlefield = $person->$itomicfield;

                // Tell our function that the user has had some changes so that we can update the user in moodle.
                $haschanges = true;
            }
        }

        // If there wasn't any changes then we can just finish here.
        if (!$haschanges) {
            return false;
        }

        // Todo: May need to do way more error checking here, but what?
        if (!$this->fields_below_max_length('user', $moodleuser)) {
            return false;
        }

        // Use the user lib to update our user.
        user_update_user($moodleuser, false);

        if (core_text::strtolower($person->auth) === 'openid') {
            // When the username has changed we need to update the openid_urls table. Unfortunately there isn't a function for this
            // (I may not have looked properly or at all :joy:) so lets just be cool and do it manually :).
            if ($usernamehaschanged) {
                // Get the existing data. Update the openid_url only if the record for that user exists. It should though.
                if ($openidurl = $DB->get_record('openid_urls', array('userid' => $moodleuser->id))) {
                    // Get the openid_url_userfield. This will be used to construct the updated openid_url for this user.
                    $openidurluserfield = get_config('local_mitowebservices', 'openid_url_userfield');

                    // Change the existing records url value to reflect the username change.
                    $openidurl->url = $this->openidurl . $moodleuser->$openidurluserfield;

                    // Update the openid_urls table with the updated openid_url.
                    $DB->update_record('openid_urls', $openidurl);

                    // Output a message to notify of openid_url change.
                    // Todo: This would be really cool if it was an event trigger? Or not. But for now I am not that cool :(.
                    $outputmessage = $this->get_string(
                        'update_person', "Updated openid_url for ITOMIC user with id {$person->id} because of username change"
                    );

                    mtrace($outputmessage);
                }
            }
        }

        // Get moodle userid from the user table
        $mdluser = $DB->get_record('user', ['idnumber' => $person->id]);

        //Update assessor group
        if ($assessorgroupchanges) {
            $userdata = $DB->get_record('user_info_data', ['userid' => $mdluser->id, 'fieldid' => '6']);
            $userdata->data = $person->totaraassessorgroup;
            $DB->update_record('user_info_data', $userdata);

            echo 'Assessor group updated to: ' . $person->totaraassessorgroup;
        }
        //Create new assessor group record
        if ($newassessorgrouprecord){
            $infodatamappings = array(
                'userid'            => $mdluser->id,
                'fieldid'           => '6',
                'data'              => $person->totaraassessorgroup,
                'dataformat'        => '0'
            );
            $DB->insert_record('user_info_data', $infodatamappings);

            echo 'Assessor group record created and updated to: ' . $person->totaraassessorgroup;
        };

        // Update local mapping.
        $this->map_object('user', $moodleuser->id, $person->id, false, true);

        // Check for user suspension and suspend if necessary.
        if (isset($moodleuser->suspended) && $moodleuser->suspended) {
            manager::kill_user_sessions($moodleuser->id);

            // Trigger a user suspended event.
            user_suspended::create_from_user($moodleuser)->trigger();
        }

        // Tell the calling function that we updated the user.
        return true;
    }

    /**
     * Converts the ITOMIC webservice person object into a moodle user as a stdClass object.
     *
     * @param   stdClass $person   The ITOMIC webservice person object to create a moodle user from.
     * @throws  \exception          Throws an exception when the function is called incorrectly.
     * @return  stdClass
     */
    private function construct_person(stdClass $person) {
        // Check that the length of the person objects field values doesn't exceed the length of the field in the db table that
        // we are inserting to.

        // Using the ITOMIC webservice response person object we will construct a stdClass that represents a user object that we
        // can create a moodle user from :).
        $itomicperson               = new stdClass();

        // These fields will be used to identify the ITOMIC user.
        $itomicperson->username     = $person->username;
        $itomicperson->idnumber     = $person->id;

        // The ITOMIC users personal information.
        $itomicperson->email        = $person->email;
        $itomicperson->firstname    = $person->firstname;
        $itomicperson->lastname     = $person->lastname;
        $itomicperson->city         = "*";

        // Set these fields with default values and override them with values from the ITOMIC webservice response if they exist.
        $itomicperson->department   = '';
        $itomicperson->timemodified = 0;
        $itomicperson->phone1       = '';
        $itomicperson->phone2       = '';


        //Set the authentication to the value provided by CRM - SHOULD ONLY BE OIDC
        if ($person->auth) {
            $itomicperson->auth = $person->auth;
        }

        // If the webservice response contains a value for organisation then we will update the value for our prepared user object.
        if ($person->organisation) {
            $itomicperson->department = $person->organisation;
        }

        // If the webservice response contains a value for modified then we will update the value for our prepared user object.
        if ($person->modified) {
            $itomicperson->timemodified = $person->modified;
        }

        // If the webservice response contains a value for mobile then we will update the value for our prepared user object.
        if ($person->mobile) {
            $itomicperson->phone1 = $person->mobile;
        }

        // If the webservice response contains a value for landline then we will update the value for our prepared user object.
        if ($person->landline) {
            $itomicperson->phone2 = $person->landline;
        }

        if (isset($person->status) && ($person->status == self::USER_NOT_SUSPENDED || $person->status == self::USER_SUSPENDED)) {
            // Set the status based on what was received from ITOMIC or set as a default for the fallback if not set.
            $itomicperson->suspended = $person->status;
        } else {
            // Default is not suspended. This is in case things aren't passed from ITOMIC.
            $itomicperson->suspended = self::USER_NOT_SUSPENDED;
        }

        // The user object is ready now.
        return $itomicperson;
    }
}