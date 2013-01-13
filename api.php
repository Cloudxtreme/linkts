<?php
## Link Tracking System
## Copyleft - Damian Kęska
##
## This program is free software: you can redistribute it and/or modify
## it under the terms of the GNU General Public License as published by
## the Free Software Foundation, either version 3 of the License, or
## (at your option) any later version.
## 
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
## 
## You should have received a copy of the GNU General Public License
## along with this program.  If not, see <http://www.gnu.org/licenses/>.

include('core/user.class.php');
include('core/lib.php');
include('core/linkmanagement.class.php');
include('data/config.php');

$UserID = @$_GET['uid'];
$Token = @$_GET['api'];
$Link = @$_GET['url'];
$ID = @$_GET['id'];
$Action = @$_GET['action'];

$User = new User();

if ($User -> authorize ($UserID, $Token) )
{
    $Links = new LinkManagement($Config['db']);
    $Exists = $Links -> exists($Link);

    // actions when url exists
    switch ($Action) {
        case "submit":

            if ($Exists == False)
            {
                $ID = $Links -> add($Link, $_SERVER['REMOTE_ADDR'], getRealIpAddr(), "now", $UserID, @$_GET['linkid']);
                print(json_encode(array('response' => 'Done', 'id' => str_ireplace('{$link}', $ID, $Config['external_url']), 'code' => 1, 'err' => False)));  
            } else
                print(json_encode(array('response' => 'Link already exists', 'code' => 2, 'err' => True)));   
        break;

        case "list":
            $Result = $Links -> getLinksByUid($UserID);
            print(json_encode(array('response' => $Result, 'code' => 4, 'err' => False)));   
        break;

        case "check":
            $Result = $Links -> getUserLink($ID, $UserID);
            print(json_encode(array('response' => $Result, 'code' => 4, 'err' => False)));   
        break;

        case "visits":
            $Result = $Links -> getVisitsById($UserID, $ID);
            print(json_encode(array('response' => $Result, 'code' => 5, 'err' => False)));
        break;

        default:
            print(json_encode(array('response' => 'Action not recignized.', 'code' => 6, 'err' => True)));
        break;
    }
} else
    print(json_encode(array('response' => 'Invalid user name or password', 'code' => 3, 'err' => True)));

?>
