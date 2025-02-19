<?php
/**
 * Control Panel Permissions plugin for Craft CMS 3.x
 *
 * A plugin that allows admins to set tab and field restrictions for particular user groups in the system. For example, an admin could create a tabbed section that only they could see when creating entries.
 *
 * @link      https://joshsmith.dev
 * @copyright Copyright (c) 2019 Josh Smith
 */

namespace thejoshsmith\fabpermissions\services;

use Craft;
use craft\base\Component;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\base\Field;
use craft\web\User;
use yii\base\Exception;

use thejoshsmith\fabpermissions\records\FabPermissionsRecord;

/**
 * Fab Permissions Service
 * @author    Josh Smith
 * @package   FabPermissions
 * @since     1.0.0
 */
class Fab extends Component
{
    /**
     * Define the permission handles
     * @var string
     */
    public static $adminPermissionHandle = 'admin';
    public static $viewPermissionHandle = 'canView';
    public static $editPermissionHandle = 'canEdit';

    /**
     * Returns Fab Permission records matching the passed criteria
     * @author Josh Smith <me@joshsmith.dev>
     * @param  array  $criteria An array of criteria filters
     * @return array
     */
    public function getPermissions($criteria = []) : array
    {
        $currentSite = Craft::$app->sites->getCurrentSite();
        $criteria['siteId'] = $currentSite->id;
        $fabPermissions = FabPermissionsRecord::findAll($criteria);

        return (empty($fabPermissions) ? [] : $fabPermissions);
    }

    /**
     * Returns whether the passed user has permission to view the passed tab for the current site.
     * @author Josh Smith <me@joshsmith.dev>
     * @param  FieldLayoutTab $tab         Tab object
     * @param  User           $user        User object
     * @param  Site           $currentSite Site object
     * @return boolean
     */
    public function canViewTab(FieldLayoutTab $tab, User $user, $currentSite = null)
    {
        if( $user->getIsAdmin() ) return true;
        if( $currentSite === null ) $currentSite = Craft::$app->sites->getCurrentSite();

        // Fetch permission records
        $fabPermissions = FabPermissionsRecord::findAll([
            'layoutId' => $tab->getLayout()->id,
            'tabId' => $tab->id,
            'siteId' => $currentSite->id
        ]);

        // Return true if no permissions have been set on this tab
        if( empty($fabPermissions) ) return true;

        // Loop the permissions and determine if the user can see the tab
        foreach ($fabPermissions as $fabPermission) {
            $isUserInGroup = $user->getIdentity()->isInGroup($fabPermission->userGroupId);
            if( $isUserInGroup && (bool) $fabPermission->{self::$viewPermissionHandle} === true ){
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the passed user has permission to view the passed field for the current site
     * @author Josh Smith <me@joshsmith.dev>
     * @param  int     $layoutId    Layout ID
     * @param  Field   $field       Field object
     * @param  User    $user        User object
     * @param  Site    $currentSite Site object
     * @return boolean
     */
    public function canViewField(int $layoutId, Field $field, User $user, $currentSite = null)
    {
        if( $user->getIsAdmin() ) return true;
        if( $currentSite === null ) $currentSite = Craft::$app->sites->getCurrentSite();

         // Fetch permission records
        $fabPermissions = FabPermissionsRecord::findAll([
            'layoutId' => $layoutId,
            'fieldId' => $field->id,
            'siteId' => $currentSite->id
        ]);

        // Return true if no permissions have been set on this tab
        if( empty($fabPermissions) ) return true;

        // Loop the permissions and determine if the user can see the tab
        foreach ($fabPermissions as $fabPermission) {
            $isUserInGroup = $user->getIdentity()->isInGroup($fabPermission->userGroupId);
            if( $isUserInGroup && (bool) $fabPermission->{self::$viewPermissionHandle} === true ){
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether the passed user has permission to view the passed field for the current site
     * @author Josh Smith <me@joshsmith.dev>
     * @param  int     $layoutId    Layout ID
     * @param  Field   $field       Field object
     * @param  User    $user        User object
     * @param  Site    $currentSite Site object
     * @return boolean
     */
    public function canEditField(int $layoutId, Field $field, User $user, $currentSite = null)
    {
        if( $user->getIsAdmin() ) return true;
        if( $currentSite === null ) $currentSite = Craft::$app->sites->getCurrentSite();

         // Fetch permission records
        $fabPermissions = FabPermissionsRecord::findAll([
            'layoutId' => $layoutId,
            'fieldId' => $field->id,
            'siteId' => $currentSite->id
        ]);

        // Return true if no permissions have been set on this tab
        if( empty($fabPermissions) ) return true;

        // Loop the permissions and determine if the user can see the tab
        foreach ($fabPermissions as $fabPermission) {
            $isUserInGroup = $user->getIdentity()->isInGroup($fabPermission->userGroupId);
            if( $isUserInGroup && (bool) $fabPermission->{self::$editPermissionHandle} === true ){
                return true;
            }
        }

        return false;
    }

    /**
     * Saves permissions from the passed field layout object
     * @author Josh Smith <me@joshsmith.dev>
     * @param  FieldLayout $layout Field layout object
     * @return void
     */
    public function saveFieldLayoutPermissions(FieldLayout $layout)
    {
        $request = Craft::$app->getRequest();
        $hasPostData = $request->post('tabPermissions') || $request->post('fieldPermissions');
        $tabPermissions = $request->post('tabPermissions') ?? [];
        $fieldPermissions = $request->post('fieldPermissions') ?? [];

        // we can't continue if there's no post data
        if( empty($hasPostData) ) return false;

        $fabPermissionsData = [];
        $currentSite = Craft::$app->sites->getCurrentSite();

        // Loop tabs and work out permissions
        foreach ($layout->getTabs() as $tab) {
            foreach ($tabPermissions as $tabName => $permissions) {

                if( urldecode($tabName) !== $tab->name ) continue;

                // Fetch the user group ID
                foreach ($permissions as $handle => $permissions) {

                    // Detect the User Group Id
                    $userGroupId = $this->getUserGroupIdFromHandle($handle);
                    $canViewValue = ($userGroupId === null ? '1' : $permissions[self::$viewPermissionHandle]);
                    // $canEditValue = ($userGroupId === null ? '1' : $permissions[self::$editPermissionHandle]);

                    $fabPermissionsData[] = [
                        $layout->id,
                        $tab->id,
                        null,
                        $currentSite->id,
                        $userGroupId,
                        (isset($canViewValue) ? $canViewValue : null),
                        null
                        // (isset($canEditValue) ? $canEditValue : null),
                    ];
                }
            }
        }

        // Loop field permissions and work out permissions
        foreach ($fieldPermissions as $fieldId => $values) {
            foreach ($values as $handle => $permissions) {

                // Detect the User Group Id
                $userGroupId = $this->getUserGroupIdFromHandle($handle);
                $canViewValue = ($userGroupId === null ? '1' : $permissions[self::$viewPermissionHandle]);
                $canEditValue = ($userGroupId === null ? '1' : $permissions[self::$editPermissionHandle]);

                $fabPermissionsData[] = [
                    $layout->id,
                    null,
                    $fieldId,
                    $currentSite->id,
                    $userGroupId,
                    (isset($canViewValue) ? $canViewValue : null),
                    (isset($canEditValue) ? $canEditValue : null),
                ];
            }
        }

        // Determine the fields to use
        $fabPermissionsRecord = new FabPermissionsRecord();
        $fields = array_values(
            array_intersect($fabPermissionsRecord->attributes(), [
                'layoutId',
                'tabId',
                'fieldId',
                'siteId',
                'userGroupId',
                self::$viewPermissionHandle,
                self::$editPermissionHandle,
            ]
        ));

        if( !empty($fabPermissionsData) ){
            Craft::$app->db->createCommand()->batchInsert(FabPermissionsRecord::tableName(), $fields, $fabPermissionsData)->execute();
        }
    }

    /**
     * Returns the user group ID from the passed handle
     * @author Josh Smith <me@joshsmith.dev>
     * @param  string $handle User group handle
     * @return integer
     */
    public function getUserGroupIdFromHandle($handle)
    {
        // Admin handle is special, and is inserted as a null value
        if( $handle === self::$adminPermissionHandle ) return $groupHandleId = null;

        // Fetch the user group
        $group = Craft::$app->getUserGroups()->getGroupByHandle($handle);
        if( empty($group) ) throw new Exception('Invalid user group handle.');

        return $group->id;
    }
}
