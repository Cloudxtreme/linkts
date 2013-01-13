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

session_start();
include('data/config.php');
include('core/linkmanagement.class.php');
include('core/lib.php');

if ($_GET['id'] == "")
{
    header("Location: start.html");
    exit;
}

$a = new LinkManagement($Config['db']);

// caching
if ($a -> useSession == True)
{
    if ($a -> sessionCheck($_GET['id']) != False)
        $Data = $a -> sessionCheck($_GET['id']);
    else
        $Data = $a -> getLink($_GET['id']);
} else
    $Data = $a -> getLink($_GET['id']);

$Link = $Data[1];
$uid = $Data[0];


if ($Link != False)
{
    $a -> visit($_GET['id'], $_SERVER['HTTP_USER_AGENT'], getRealIpAddr(), "now", @$_SERVER['HTTP_REFERER'], $Link, $uid);
    header("Location: ".$Link);
    exit;
} else {
    //print("Location: 404.html");
    header("Location: 404.html");
    exit;
}
?>
