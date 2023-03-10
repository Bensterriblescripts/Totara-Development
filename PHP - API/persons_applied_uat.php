<?php


//ENDPOINT CHANGES
//REMOVE UNNECESSARY JSON OBJECT - trainingsupervisorid
//sorry Josh...




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
            'id', 'username', 'firstname', 'lastname', 'email', 'mobile', 'landline', 'organisation', 'modified', 'totaraassessorgroup', 'trainingsupervisorid', 'agents'
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
                    "Updating user with the id {$person->id}, username {$person->username} and email {$person->email}."
                );

                $existinguser = true;

                // Output the message to the log.
                mtrace($message);

                //Get the user's moodle record
                $moodleuser = $DB->get_record('user', ['idnumber' => $person->id, 'username'=> $person->username, 'email' => $person->email]);

                // Actual perform the update.
                $this->update_person($person, $conditionsidnumber, $moodleuser);
                $this->update_profile_fields($person, $moodleuser);
                $this->update_agents($person, $moodleuser);

            } else if ($userexistsbyusernameandemail) {
                // If the user exists by username and email then we need to update them. They should have an idnumber.

                // Prepare a message for the log.
                $message = $this->get_string(
                    'process', "Updating user with the id, {$person->username} and email {$person->email}."
                );

                $existinguser = true;

                // Output the message to the log.
                mtrace($message);

                //Get the moodle record
                $moodleuser = $DB->get_record('user', ['username' => $person->username, 'email' => $person->email]);

                // Actual perform the update.
                $this->update_person($person, $conditionsusernameandemail, $moodleuser);
                $this->update_profile_fields($person, $moodleuser);
                $this->update_agents($person, $moodleuser);

            } else if ($userexistsbyidnumber) {
                // If the user exists just by idnumber then they could be missing some stuff but I highly doubt it.

                // Prepare a message for the log
                $message = $this->get_string('process', "Updating user with the idnumber {$person->id}");

                $existinguser = true;

                // Output the message to the log.
                mtrace($message);

                //Get the moodle record
                $moodleuser = $DB->get_record('user', ['idnumber' => $person->id]);

                // Actual perform the update.
                $this->update_person($person, $conditionsidnumber, $moodleuser);
                $this->update_profile_fields($person, $moodleuser);
                $this->update_agents($person, $moodleuser);

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
     * This is the second part of the ITOMIC to moodle user creation process. This is where we actually create the user.
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

        // Overwrite the old moodle userdata to get the new id and fields
        $moodleuser = $DB->get_record('user', ['idnumber' => $person->id, 'email' => $person->email, 'username' => $person->username]);

        // Call the profile field creation function
        $this->create_person_fields($person, $moodleuser);

        // Done
        return true;
    }

    /**
     * Catch issues then run then create their fields
     *
     * @param   stdClass $person   The ITOMIC webservice person object to compare against moodle's record of the associated user.
     * @throws  \exception          Throws an exception when the function is called incorrectly.
     * @return  bool                True if the person was updated or false if no changes. This shouldn't really return false unless
     *                              the caller is false :joy:.
     */
    private function create_person_fields(stdClass $person, $moodleuser = array()) {

        global $DB;

        // Catch existing records, just in case
        $abortgroup                     = false;
        $abortmitoid                    = false;
        $abortidentifier                = false;
        $abortsecondaryidentifier       = false;

        //MITO ID
        if ($DB->record_exists('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '10'])) {
            $abortmitoid = true;
        }
        else {
            // Manipulate username into MITO ID - TODO Needs to be an endpoint object
            $mitoid = substr($person->username, 0, strpos($person->username, '@'));
            $mitoidmappings = array(
                'userid'        => $moodleuser->id,
                'fieldid'       => '10',
                'data'          => $mitoid,
                'dataformat'    => '0'
            );
            $DB->insert_record('user_info_data', $mitoidmappings);
            $message = $this->get_string(
                "details",
                "MITO ID set to: {$mitoid}."
            );

            mtrace($message);
        }

        //Assessor group
        if ($DB->record_exists('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '6'])) {
            return true;
        }
        else {
            // We want to create a record for assessor group regardless of NULL values
            if (empty($person->totaraasessorgroup)) {
                $person->totaraassessorgroup = 'Not assigned';
            }
            $groupmappings = array(
                'userid'        => $moodleuser->id,
                'fieldid'       => '6',
                'data'          => $person->totaraassessorgroup,
                'dataformat'    => '0'
            );
            // Create the record
            $DB->insert_record('user_info_data', $groupmappings);
            $message = $this->get_string(
                "details",
                "Assessor group set to: {$person->totaraassessorgroup}"
            );

            mtrace($message);
        }

        // Agents
        // Determine whether this user has agents and which agent the supervisor is under
        $agent0 = false;
        $agent1 = false;

        if (!empty($person->agents[0])) {
            if ($person->agents[0]->type == 'Supervisor') {
                if ($DB->record_exists('user', ['idnumber' => $person->agents[0]->id])) {
                    $moodlesupervisor = $DB->get_record('user', ['idnumber' => $person->agents[0]->id]);
                    $agent = true;
                }
                else {
                    return false;
                }
            }
        }
        if (!empty($person->agents[1])) {
            if ($person->agents[1]->type == 'Supervisor') {
                if ($DB->record_exists('user', ['idnumber' => $person->agents[1]->id])) {
                    $moodlesupervisor = $DB->get_record('user', ['idnumber' => $person->agents[1]->id]);
                    $agent = true;
                }
                else {
                    return true;
                }
            }
        }

        // If the user has an agent, they are a learner. Time to do some updates.
        if ($agent) {

            $supervisorfield9exists     = false;
            $supervisorfield11exists    = false;

            // Existing Supervisor Identifier
            if ($DB->record_exists('user_info_data', ['userid' => $moodlesupervisor->id, 'fieldid' => '9'])) {
                $supervisorfield9 = $DB->get_record('user_info_data', ['userid' => $moodlesupervisor->id, 'fieldid' => '9']);
                $supervisorfield9exists = true;
            }
            else {
                $supervisoridentifiermappings = array(
                    'userid'        => $moodlesupervisor->id,
                    'fieldid'       => '9',
                    'data'          => $moodlesupervisor->idnumber,
                    'dataformat'    => '0'
                );
            }
            // Existing Supervisor Secondary Identifier
            if ($DB->record_exists('user_info_data', ['userid' => $moodlesupervisor->id, 'fieldid' => '11'])) {
                $supervisorfield11 = $DB->get_record('user_info_data', ['userid' => $moodlesupervisor->id, 'fieldid' => '11']);
                $supervisorfield11exists = true;
            }
            else {
                $supervisorsecondaryidentifiermappings = array(
                    'userid'        => $moodlesupervisor->id,
                    'fieldid'       => '11',
                    'data'          => $moodlesupervisor->idnumber,
                    'dataformat'    => '0'
                );
            }
            // User Identifier
            if ($DB->record_exists('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '9'])) {
                return true;
            }
            else {
                $identifiermappings = array(
                    'userid'        => $moodleuser->id,
                    'fieldid'       => '9',
                    'data'          => $moodlesupervisor->idnumber,
                    'dataformat'    => '0'
                );
            }

            // User Secondary Identifier
            if ($DB->record_exists('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '11'])) {
                return true;
            }
            else {
                $secondaryidentifiermappings = array(
                    'userid'        => $moodleuser->id,
                    'fieldid'       => '11',
                    'data'          => $moodlesupervisor->idnumber,
                    'dataformat'    => '0'
                );
            }

            // Big logic incoming

            // The supervisor field does not exist - Create learner and supervisor
            if (!$supervisorfield9exists) { 

                // Create the learner's record
                $DB->insert_record('user_info_data', $identifiermappings);
                $message = $this->get_string(
                    "details",
                    "This user now has the supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname} assigned to them."
                );
                mtrace($message);

                // Now create the supervisor record
                $DB->insert_record('user_info_data', $supervisoridentifiermappings);
                $message = $this->get_string(
                    "details",
                    "The supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname} had their identifier field created and set to this user."
                );
                mtrace($message);
            }

            // The supervisor has an existing field and it is not linked to another supervisor - Create learner
            if ($supervisorfield9exists && $supervisorfield9->data == $person->trainingsupervisorid) {
                // Create the learner's record
                $DB->insert_record('user_info_data', $identifiermappings);
                $message = $this->get_string(
                    "details",
                    "The supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname} had their identifier field created and set to this user."
                );
                mtrace($message);

                // Remove existing secondary field for supervisor if it exists, we know they are only a supervisor
                if ($supervisorfield11exists) {
                    $supervisorfield11->data = 'Not assigned';
                    $DB->update_record('user_info_data', $supervisorfield11);
                    $message = $this->get_string(
                        "details",
                        "The supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname} has had their secondary identifier field set to default"
                    );
                    mtrace($message);

                }
                // Clean up the identifier field, we know the learner is the last in the chain
                if ($DB->record_exists('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '11'])) {
                    $userfield11 = $DB->get_record('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '11']);
                    $userfield11->data = 'Not assigned';
                    $DB->update_record('user_info_data', $userfield11);

                    $message = $this->get_string(
                        "details",
                        "This user has had their secondary identifier set to default"
                    );
                    mtrace($message);
                }
            }

            // The supervisor has an existing field and it is linked to a supervisor - Create learner and supervisor under the secondary field
            if ($supervisorfield9exists && $supervisorfield9->data != $person->trainingsupervisorid) {

                // Set up the learner's field
                $DB->insert_record('user_info_data', $secondaryidentifiermappings);
                $message = $this->get_string(
                    "details",
                    "The supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname} has been assigned to this learner under the secondary identifier."
                );
                mtrace($message);

                // Set up the supervisor's field
                if (!$supervisorfield11exists) {
                    $DB->insert_record('user_info_data', $supervisorsecondaryidentifiermappings);
                    $message = $this->get_string(
                        "details",
                        "The supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname} has had a secondary field created."
                    );
                    mtrace($message);
                }
            }
        }

        // Done!
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
    private function update_person(stdClass $person, $conditions, $moodleuser = array()) {

        global $DB;

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

        // Find the authentication for this person.
        $conditions['auth'] = $person->auth;
        
        if (!$moodleuser) {
            // The person we are trying to update doesn't exist in moodle. This should never happen but we will catch it anyway.
            $errormessage = $this->get_string(
                'update_person', "The person with id {$person->id} you are trying to update doesn't exist in moodle."
            );

            // Output the error message.
            mtrace($errormessage);
            return false;
        }


        // The user exists in moodle so lets look for any changes and update where necessary.
        $haschanges = false;

        // Has the username been updated? We initially think that it hasn't.
        $usernamehaschanged = false;

        //Change auth to string
        $person->auth = core_text::strtolower($person->auth);

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
    *   Profile fields sync
    *
    *   Update or create a user_info_data record for user - Located on the website in a user's profile page under 'other fields'
    *   
    *   trainingsupervisorid    - fieldid 9 - id for the supervisor that is currently assigned to the record, will be blank if none 
    *   totaraassessorgroup     - fieldid 6 - assessor group in the 'portal' area of a contact record in CRM
    *   mitoid - TEMP           - fieldid 10 - username without the @ portion
    *
    *   If the user is a supervisor they will assign their own ID to themselves to auto-group them and their learners by ID
    *   Todo: Fix the above, use the agents (array?) of a supervisor JSON push to sync supervisors correctly.
    *
    * @param   stdClass $person   
    * @throws  \exception          
    * @return  bool                
    *                              
    */

    private function update_profile_fields(stdClass $person, $moodleuser = array()) {

        global $DB;

        // Manipulate username into MITO ID - TODO Needs to be an endpoint object
        $mitoid = substr($person->username, 0, strpos($person->username, '@'));
        //Set a new value for assessorgroup, if it's empty set it to default
        if (empty($person->totaraassessorgroup)) {
            $person->totaraassessorgroup = 'Not assigned';
        }

        // Does the user have existing records?
        $mitoidfieldexists = $DB->record_exists('user_info_data',['userid' => $moodleuser->id, 'fieldid' => '10']);
        $assessorgroupfieldexists = $DB->record_exists('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '6']);
        if ($assessorgroupfieldexists) {
            $currentrecord = $DB->get_record('user_info_data', ['userid' => $moodleuser->id, 'fieldid' =>'6']);
        }
        
        // Default MITO ID field mappings
        $mitoidmappings = array(
            'userid'        => $moodleuser->id,
            'fieldid'       => '10',
            'data'          => 'Pending Sync',
            'dataformat'    => '0'
        );
        // Default assessor group field mappings
        $assessorgroupmappings = array(
            'userid'        => $moodleuser->id,
            'fieldid'       => '6',
            'data'          => $person->totaraassessorgroup,
            'dataformat'    => '0'

        );

        // If there is, determine whether it needs changing
        if ($mitoidfieldexists) {

            $mitoidfield = $DB->get_record('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '10']);

            // If it doesn't match, update the record
            if ($mitoidfield->data != $mitoid) {
                $mitoidfield->data = $mitoid;
                $DB->update_record('user_info_data', $mitoidfield);

                $message = $this->get_string(
                    "details",
                    "MITO ID set to {$mitoid}"
                );

                mtrace($message);
            }
        }
        // If it doesn't exist, create it
        else if (!$mitoidfieldexists) {

            //If the endpoint value isn't empty make the new record
            if (!empty($mitoid)) {
                
                $mitoidmappings->data = $mitoid;
                $DB->insert_record('user_info_data', $mitoidmappings);

                $message = $this->get_string(
                    "details",
                    "MITO ID set to {$mitoid}"
                );

                mtrace($message);
            }
        }

        // Assessor Group - Update
        if ($assessorgroupfieldexists && $currentrecord->data != $person->totaraassessorgroup) {
            // Update the record with the new value
            $currentrecord->data = $person->totaraassessorgroup;
            $DB->update_record('user_info_data', $currentrecord);

            $message = $this->get_string(
                "details",
                "Assessor group set to: {$person->totaraassessorgroup}"
            );

            mtrace($message);

        }
        else if (!$assessorgroupfieldexists) {
            // Create the field if it doesn't exist, regardless of value.
            $DB->insert_record('user_info_data', $assessorgroupmappings);

            $message = $this->get_string(
                "details",
                "Assessor group created and set to: {$person->totaraassessorgroup}"
            );

            mtrace($message);

        }

        // Let our function know we're done.
        return true;
    }

    /**
    *   Learner/Supervisor fields sync for auto-grouping the two
    *   Field 9     - Identifier
    *   Field 11    - Secondary Identifier
    *
    *
    * @param   stdClass $person   
    * @throws  \exception          
    * @return  bool                
    *                              
    */

    private function update_agents(stdClass $person, $moodleuser = array()) {

        global $DB;

        if (!$moodleuser) {
            // The person we are trying to update doesn't exist in moodle. This should never happen but we will catch it anyway.
            $errormessage = $this->get_string(
                'update_person', "The person with id {$person->id} you are trying to update doesn't exist in moodle."
            );

            // Output the error message.
            mtrace($errormessage);
            return false;
        }

        // Booleans to avoid creating more queries than necessary
        $supervisorfield9exists     = false;
        $supervisorfield11exists    = false;
        $supervisorroleexists       = false;

        // Find the state and correct type of agent for this user
        if (!empty($person->agents[0])) {
            if ($person->agents[0]->type == 'Supervisor') {
                if ($DB->record_exists('user', ['email' => $person->agents[0]->email, 'idnumber' => $person->agents[0]->id])) {
                    $moodlesupervisor = $DB->get_record('user', ['email' => $person->agents[0]->email, 'idnumber' => $person->agents[0]->id]);
                }
                else {
                    // We have nothing to do, return.
                    $message = $this->get_string(
                        'details', 
                        "This user's supervisor does not yet exist in moodle."
                    );
    
                    mtrace($message);
                    return true;
                }
            }
        }
        else if (!empty($person->agents[1])) {
            if ($person->agents[1]->type == 'Supervisor') {
                if ($DB->record_exists('user', ['email' => $person->agents[0]->email, 'idnumber' => $person->agents[1]->id])) {
                    $moodlesupervisor = $DB->get_record('user', ['email' => $person->agents[0]->email, 'idnumber' => $person->agents[1]->id]);
                }
                else {
                    // We have nothing to do, return.
                    $message = $this->get_string(
                        'details', 
                        "This user's supervisor does not yet exist in moodle."
                    );
    
                    mtrace($message);
                    return true;
                }
            }
        }
        else {
            $message = $this->get_string(
                'details', 
                "This user is a supervisor, their learners will be assigned during the learner sync"
            );

            mtrace($message);
            return true;
        }
        
        // Generate some default/empty field records for the user and supervisor (if they exist)
        //User - Field 9 - Identifier
        $usernewfield9 = array (
            'userid'        => $moodleuser->id,
            'fieldid'       => '9',
            'data'          => 'Not assigned',
            'dataformat'    => '0'
        );

        //User - Field 11 - Secondary Identifier
        $usernewfield11 = array (
            'userid'        => $moodleuser->id,
            'fieldid'       => '11',
            'data'          => 'Not assigned',
            'dataformat'    => '0'
        );
        //Supervisor - Field 9 - Identifier
        $supervisornewfield9 = array (
            'userid'        => $moodlesupervisor->id,
            'fieldid'       => '11',
            'data'          => $moodlesupervisor->idnumber,
            'dataformat'    => '0'
        );
        //Supervisor - Field 11 - Secondary Identifier
        $supervisornewfield11 = array (
            'userid'        => $moodlesupervisor->id,
            'fieldid'       => '11',
            'data'          => $moodlesupervisor->idnumber,
            'dataformat'    => '0'
        );

        // Get the user's fields and if they exist
        $userfield9exists               = $DB->record_exists('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '9']);
        if ($userfield9exists) {
            $userfield9                 = $DB->get_record('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '9']);
        }
        $userfield11exists              = $DB->record_exists('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '11']);
        if ($userfield11exists) {
            $userfield11                = $DB->get_record('user_info_data', ['userid' => $moodleuser->id, 'fieldid' => '11']);
        }
        
        // Get the supervisor's fields and if they exist
        $supervisorfield9exists     = $DB->record_exists('user_info_data', ['userid' => $moodlesupervisor->id, 'fieldid' => '9']);
        if ($supervisorfield9exists) {
            $supervisorfield9       = $DB->get_record('user_info_data', ['userid' => $moodlesupervisor->id, 'fieldid' => '9']);
        }
        $supervisorfield11exists    = $DB->record_exists('user_info_data', ['userid' => $moodlesupervisor->id, 'fieldid' => '11']);
        if ($supervisorfield11exists) {
            $supervisorfield11      = $DB->get_record('user_info_data', ['userid' => $moodlesupervisor->id, 'fieldid' => '11']);
        }

        // Does the supervisor need a role update as well?
        $supervisorroleexists = $DB->record_exists('role_assignments', ['userid' => $moodlesupervisor->id, 'roleid' => '46', 'contextid' => '1']);




        //
        // Learner, no supervisor field conflict - Set self and supervisor
        //

        // The field is not linked to another user
        if ($supervisorfield9->data == 'Not assigned' || $supervisorfield9->data == $person->trainingsupervisorid || !$supervisorfield9) {

            // Set up the user
            if ($userfield9exists && $userfield9->data != $moodlesupervisor->idnumber) {
                $userfield9->data = $moodlesupervisor->idnumber;
                $DB->update_record('user_info_data', $userfield9);

                $message = $this->get_string(
                    'details', 
                    "Assigned the supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname}."
                );

                mtrace($message);
            }

            else if (!$userfield9exists) {
                $usernewfield9->data = $moodlesupervisor->idnumber;
                $DB->insert_record('user_info_data', $usernewfield9);

                $message = $this->get_string(
                    'details', 
                    "Assigned the supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname}."
                );

                mtrace($message);
            }

            // Set up the supervisor
            if ($supervisorfield9exists && $supervisorfield9->data != $moodlesupervisor->idnumber) {
                $supervisorfield9->data = $moodlesupervisor->idnumber;
                $DB->update_record('user_info_data', $supervisorfield9);

                $message = $this->get_string(
                    'details', 
                    "Set up their supervisor's identifier field."
                );

                mtrace($message);
            }
            else if (!$supervisorfield9exists) {
                $supervisornewfield9 ->data = $moodlesupervisor->idnumber;
                $DB->insert_record('user_info_data', $supervisornewfield9);

                $message = $this->get_string(
                    'details', 
                    "Set up their supervisor's identifier field."
                );

                mtrace($message);
            }

            // Clean up old fields if no longer relevant
            if ($userfield11exists && $userfield11->data != 'Not assigned') {
                $userfield11->data = 'Not assigned';
                $DB->update_record('user_info_data', $userfield11);
                $message = $this->get_string(
                    'details', 
                    "Secondary identifier has been set to default."
                );

                mtrace($message);
            }
            if ($supervisorfield11exists && $supervisorfield11->data != 'Not assigned') {
                $supervisorfield11->data = 'Not assigned';
                $DB->update_record('user_info_data', $supervisorfield11);
                $message = $this->get_string(
                    'details', 
                    "Their supervisor has had their secondary identifier set to default."
                );

                mtrace($message);
            }
        }

        //
        // Learner has a supervisor with a field conflict - Set self and supervisor
        //
        
        // The supervisor's field is linked to another supervisor.
        else if ($supervisorfield9exists && $supervisorfield9->data != $person->trainingsupervisorid) {

            // Set up the user
            if ($userfield11exists && $userfield11->data != $moodlesupervisor->idnumber) {
                $userfield11->data = $moodlesupervisor->idnumber;
                $DB->update_record('user_info_data', $userfield11);

                $message = $this->get_string(
                    'details', 
                    "Assigned the supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname} in the secondary identifier field"
                );

                mtrace($message);
            }

            else if (!$userfield11exists) {
                $usernewfield11->data = $moodlesupervisor->idnumber;
                $DB->insert_record('user_info_data', $usernewfield11);

                $message = $this->get_string(
                    'details', 
                    "Assigned the supervisor {$moodlesupervisor->firstname} {$moodlesupervisor->lastname} in the secondary identifier field"
                );
                    
                mtrace($message);
            }

            // Set up the supervisor
            if ($supervisorfield11exists && $supervisorfield11->data != $moodlesupervisor->idnumber) {
                $supervisorfield11->data = $moodlesupervisor->idnumber;
                $DB->update_record('user_info_data', $supervisorfield11);

                $message = $this->get_string(
                    'details', 
                    "Supervisor's secondary field has been updated."
                );
                    
                mtrace($message);
            }

            else if (!$supervisorfield11exists) {
                $supervisornewfield11->data = $moodlesupervisor->idnumber;
                $DB->insert_record('user_info_data', $supervisornewfield11);

                $message = $this->get_string(
                    'details', 
                    "Supervisor's secondary field has been updated."
                );
                    
                mtrace($message);
            }
            // Clean up older irrelevant fields
            if ($userfield9exists && $userfield9->data != 'Not assigned') {
                $userfield9->data = 'Not assigned';
                $DB->update_record('user_info_data', $userfield9);
                $message = $this->get_string(
                    'details', 
                    "Identifier set to default."
                );

                mtrace($message);
            }
            if ($supervisorfield9exists && $supervisorfield9->data != 'Not assigned') {
                $supervisorfield9->data = 'Not assigned';
                $DB->update_record('user_info_data', $supervisorfield9);
                $message = $this->get_string(
                    'details', 
                    "Their supervisor's identifier was set to default."
                );

                mtrace($message);
            }

        }


        // Role update
        // Give the supervisor the verifier role if they don't have it already
        if (!$supervisorroleexists) {
            $verifierrole = array (
                'roleid'        => '46',
                'contextid'     => '1',
                'userid'        => $moodlesupervisor->id,
                'timemodified'  => time(),
                'modifierid'    => '25089', // Ben - Default admin
                'itemid'        => '0',
                'sortorder'     => '0'
            );
            $DB->insert_record('role_assignments', $verifierrole);
            $message = $this->get_string(
                "details",
                "Gave the verifier role to this user's supervisor"
            );
            mtrace($message);                  
        }

        //Done
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
