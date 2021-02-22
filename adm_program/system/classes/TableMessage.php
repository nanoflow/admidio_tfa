<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_messages
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class manages the set, update and delete in the table adm_messages
 */
class TableMessage extends TableAccess
{
    const MESSAGE_TYPE_EMAIL = 'EMAIL';
    const MESSAGE_TYPE_PM    = 'PM';

    /**
     * @var int
     */
    protected $msgId;
    /**
     * @var array Array with all recipients of the message.
     */
    protected $msgRecipientsArray = array();
    /**
     * @var Array with TableAcess objects
     */
    protected $msgRecipientsObjectArray = array();
    /**
     * @var Object of TableAcess for the current content of the message.
     */
    protected $msgContentObject;

    /**
     * Constructor that will create an object of a recordset of the table adm_messages.
     * If the id is set than the specific message will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $msgId    The recordset of the message with this conversation id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $msgId = 0)
    {
        $this->msgId = $msgId;

        parent::__construct($database, TBL_MESSAGES, 'msg', $this->msgId);
    }

    public function addRole($roleId, $roleMode)
    {
        // first search if role already exists in recipients list
        foreach($this->msgRecipientsObjectArray as $messageRecipientObject)
        {
            if($messageRecipientObject->getValue('msr_rol_id') === $roleId)
            {
                // if object found than update role mode and exist function
                $messageRecipientObject->setValue('msr_role_mode', $roleMode);
                return;
            }
        }

        // save message recipient as TableAcess object to the array
        $messageRecipient = new TableAccess($this->db, TBL_MESSAGES_RECIPIENTS, 'msr');
        $messageRecipient->setValue('msr_msg_id', $this->getValue('msg_id'));
        $messageRecipient->setValue('msr_rol_id', $roleId);
        $messageRecipient->setValue('msr_role_mode', $roleMode);
        $this->msgRecipientsObjectArray[] = $messageRecipient;

        // now save message recipient into an simple array
        $this->msgRecipientsArray[] =
            array('type'   => 'role',
                  'id'     => $roleId,
                  'name'   => null,
                  'mode'   => $roleMode,
                  'msr_id' => null
            );
    }

    public function addUser($userId)
    {
        // PM always update the recipient if the message exists
        if($this->getValue('msg_type') === TableMessage::MESSAGE_TYPE_PM)
        {
            if(count($this->msgRecipientsObjectArray) === 1)
            {
                $this->msgRecipientsObjectArray->setValue('msr_usr_id', $userId);
                return;
            }
        }
        else // EMAIL
        {
            // first search if user already exists in recipients list and than exist function
            foreach($this->msgRecipientsObjectArray as $messageRecipientObject)
            {
                if($messageRecipientObject->getValue('msr_usr_id') === $userId)
                {
                    return;
                }
            }
        }

        // if user doesn't exists in recipient list than save recipient as TableAcess object to the array
        $messageRecipient = new TableAccess($this->db, TBL_MESSAGES_RECIPIENTS, 'msr');
        $messageRecipient->setValue('msr_msg_id', $this->getValue('msg_id'));
        $messageRecipient->setValue('msr_usr_id', $userId);
        $this->msgRecipientsObjectArray[] = $messageRecipient;

        // now save message recipient into an simple array
        $this->msgRecipientsArray[] =
            array('type'   => 'user',
                  'id'     => $userId,
                  'name'   => null,
                  'mode'   => null,
                  'msr_id' => null
            );
    }

    /**
     * Add the content of the message or email. The content will than
     * be saved if the message will be saved.
     * @param string $content Current content of the message.
     */
    public function addContent($content)
    {
        $this->msgContentObject = new TableAccess($this->db, TBL_MESSAGES_CONTENT, 'msc');
        $this->msgContentObject->setValue('msc_msg_id', $this->getValue('msg_id'));
        $this->msgContentObject->setValue('msc_message', $content, false);
        $this->msgContentObject->setValue('msc_timestamp', DATETIME_NOW);
    }

    /**
     * Reads the number of all unread messages of this table
     * @param int $usrId
     * @return int Number of unread messages of this table
     */
    public function countUnreadMessageRecords($usrId)
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_MESSAGES . '
                 INNER JOIN ' . TBL_MESSAGES_RECIPIENTS . ' ON msr_msg_id = msg_id
                 WHERE msg_read = 1
                   AND msr_usr_id = ? -- $usrId';
        $countStatement = $this->db->queryPrepared($sql, array($usrId));

        return (int) $countStatement->fetchColumn();
    }

    /**
     * Reads the number of all conversations in this table
     * @return int Number of conversations in this table
     */
    public function countMessageConversations()
    {
        $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_MESSAGES;
        $countStatement = $this->db->queryPrepared($sql);

        return (int) $countStatement->fetchColumn();
    }

    /**
     * Reads the number of all messages in actual conversation
     * @return int Number of all messages in actual conversation
     */
    public function countMessageParts()
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_MESSAGES_CONTENT.'
                 WHERE msc_msg_id = ? -- $this->getValue(\'msg_id\')';
        $countStatement = $this->db->queryPrepared($sql, array((int) $this->getValue('msg_id')));

        return (int) $countStatement->fetchColumn();
    }

    /**
     * Deletes the selected message with all associated fields.
     * After that the class will be initialize.
     * @return bool **true** if message is deleted or message with additional information if it is marked
     *         for other user to delete. On error it is false
     */
    public function delete()
    {
        $this->db->startTransaction();

        $msgId = (int) $this->getValue('msg_id');

        if ($this->getValue('msg_type') === self::MESSAGE_TYPE_EMAIL || (int) $this->getValue('msg_read') === 2)
        {
            $sql = 'DELETE FROM '.TBL_MESSAGES_CONTENT.'
                     WHERE msc_msg_id = ? -- $msgId';
            $this->db->queryPrepared($sql, array($msgId));

            $sql = 'DELETE FROM '.TBL_MESSAGES_RECIPIENTS.'
                     WHERE msr_msg_id = ? -- $msgId';
            $this->db->queryPrepared($sql, array($msgId));

            parent::delete();
        }
        else
        {
            $sql = 'UPDATE '.TBL_MESSAGES.'
                       SET msg_read = 2
                     WHERE msg_id = ? -- $msgId';
            $this->db->queryPrepared($sql, array($msgId));
        }

        $this->db->endTransaction();

        return true;
    }

    /**
     * get a list with all messages of an conversation.
     * @param int $msgId of the conversation - just for security reasons.
     * @return false|\PDOStatement Returns **answer** of the SQL execution
     */
    public function getConversation($msgId)
    {
        $sql = 'SELECT msc_usr_id, msc_message, msc_timestamp
                  FROM '. TBL_MESSAGES_CONTENT. '
                 WHERE msc_msg_id = ? -- $msgId
              ORDER BY msc_id DESC';

        return $this->db->queryPrepared($sql, array($msgId));
    }

    /**
     * If the message type is PM this method will return the conversation partner of the PM.
     * @param int $usrId
     * @return int Returns **ID** of the user that is partner in the actual conversation or false if its not a message.
     */
    public function getConversationPartner()
    {
        global $gLogger;
        if($this->getValue('msg_type') === TableMessage::MESSAGE_TYPE_PM)
        {
            $recipients = $this->readRecipientsData();
            return $recipients[0]['id'];
        }

        return false;
    }

    /**
     * Build a string with all role names and firstname and lastname of the users.
     * The names will be semicolon separated.
     * @return string Returns a string with all role names and firstname and lastname of the users.
     */
    public function getRecipientsNamesString()
    {
        global $gCurrentUser, $gProfileFields;

        $recipients = $this->readRecipientsData();
        $recipientsString = '';

        if($this->getValue('msg_type') === TableMessage::MESSAGE_TYPE_PM)
        {
            // PM has the conversation initiator and the receiver. Here we must check which
            // role the current user has and show the name of the other user.
            if((int) $this->getValue('msg_usr_id_sender') === (int) $gCurrentUser->getValue('usr_id'))
            {
                $recipientsString = $recipients[0]['name'];
            }
            else
            {
                $user = new User($this->db, $gProfileFields, $this->getValue('msg_usr_id_sender'));
                $recipientsString = $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME');
            }
        }
        else
        {
            // email receivers are all stored in the recipients array
            foreach($recipients as $recipient)
            {
                if(strlen($recipientsString) > 0)
                {
                    $recipientsString .= '; ';
                }
                $recipientsString .= $recipient['name'];
            }
        }

        return $recipientsString;
    }

    /**
     * Method will return true if the PM was sent to the current user and not is already unread.
     * Therefore the current user is not the sender of the PM and the flag **msg_read** is set to 1.
     * Email will always have the status read.
     * @return bool Returns true if the PM was not read from the current user.
     */
    public function isUnread()
    {
        global $gCurrentUser;

        if(TableMessage::MESSAGE_TYPE_PM && $this->getValue('msg_read') === 1
        && $this->getValue('msg_usr_id_sender') != $gCurrentUser->getValue('usr_id'))
        {
            return true;
        }

        return false;
    }

    /**
     * Reads all recipients to the message and returns an array. The array has the following structure:
     * array('type' => 'role', 'id' => '4711', 'name' => 'Administrator', 'mode' => '0')
     * Type could be **role** or **user**, the id will be the database id of role or user and the
     * mode will be only used with roles and the following values are used:
     + 0 = active members, 1 = former members, 2 = active and former members
     * @return array Returns an array with all recipients (users and roles)
     */
    public function readRecipientsData()
    {
        global $gCurrentUser, $gProfileFields;

        if(count($this->msgRecipientsArray) === 0)
        {
            $sql = 'SELECT msg_usr_id_sender, msr_id, msr_rol_id, msr_usr_id, msr_role_mode, rol_name, first_name.usd_value AS firstname, last_name.usd_value AS lastname
                      FROM ' . TBL_MESSAGES . '
                     INNER JOIN ' . TBL_MESSAGES_RECIPIENTS . ' ON msr_msg_id = msg_id
                      LEFT JOIN ' . TBL_ROLES . ' ON rol_id = msr_rol_id
                      LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                             ON last_name.usd_usr_id = msr_usr_id
                            AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                      LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                             ON first_name.usd_usr_id = msr_usr_id
                            AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                    WHERE msg_id = ? -- $this->getValue(\'msg_id\') ';
            $messagesRecipientsStatement = $this->db->queryPrepared($sql,
                array($gProfileFields->getProperty('LAST_NAME', 'usf_id'), $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), $this->getValue('msg_id')));

            while($row = $messagesRecipientsStatement->fetch())
            {
                // save message recipient as TableAcess object to the array
                $messageRecipient = new TableAccess($this->db, TBL_MESSAGES_RECIPIENTS, 'msr');
                $messageRecipient->setArray($row);
                $this->msgRecipientsObjectArray[] = $messageRecipient;

                // now save message recipient into an simple array
                if($row['msr_usr_id'] > 0)
                {
                    $recipientUsrId = (int) $row['msr_usr_id'];

                    // PMs could have the current user as recipient than the sender is the recipient for this user
                    if($this->getValue('msg_type') === TableMessage::MESSAGE_TYPE_PM
                    && $recipientUsrId == $gCurrentUser->getValue('usr_id'))
                    {
                        $recipientUsrId = (int) $row['msg_usr_id_sender'];
                    }

                    // add role to recipients
                    $this->msgRecipientsArray[] =
                        array('type'   => 'user',
                              'id'     => $recipientUsrId,
                              'name'   => $row['firstname'] . ' ' . $row['lastname'],
                              'mode'   => 0,
                              'msr_id' => (int) $row['msr_id']
                        );
                }
                else
                {
                    // add user to recipients
                    $this->msgRecipientsArray[] =
                        array('type'   => 'role',
                              'id'     => (int) $row['msr_rol_id'],
                              'name'   => $row['rol_name'],
                              'mode'   => (int) $row['msr_role_mode'],
                              'msr_id' => (int) $row['msr_id']
                        );
                }
            }
        }

        return $this->msgRecipientsArray;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * For new records the name intern will be set per default.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @throws AdmException
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentUser;

        if ($this->newRecord)
        {
            // Insert
            $this->setValue('msg_timestamp', DATETIME_NOW);
        }

        $returnValue = parent::save($updateFingerPrint);

        if($returnValue)
        {
            // now save every recipient
            foreach($this->msgRecipientsObjectArray as $msgRecipientsObject)
            {
                $msgRecipientsObject->setValue('msr_msg_id', $this->getValue('msg_id'));
                $msgRecipientsObject->save();
            }

            if(is_object($this->msgContentObject))
            {
                // now save the message to the database
                $this->msgContentObject->setValue('msc_msg_id', $this->getValue('msg_id'));
                $this->msgContentObject->setValue('msc_usr_id', $gCurrentUser->getValue('usr_id'));
                $returnValue = $this->msgContentObject->save();
            }
        }

        return $returnValue;
    }

    /**
     * Set the status of the message to read. Also the global menu will be initalize to update
     * the read badge of messages.
     * @return false|\PDOStatement Returns **answer** of the SQL execution
     */
    public function setReadValue()
    {
        global $gMenu;

        if($this->getValue('msg_read') > 0)
        {
            $sql = 'UPDATE '.TBL_MESSAGES.'
                       SET msg_read = 0
                     WHERE msg_id   = ? -- $this->msgId ';

            $gMenu->initialize();
            return $this->db->queryPrepared($sql, array($this->msgId));
        }
    }
}
