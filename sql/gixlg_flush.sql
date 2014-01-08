USE `gixlg`;
TRUNCATE `prefixes`;
UPDATE `members` SET `status`='0',`time`='0000-00-00 00:00:00',`lastup`='0000-00-00 00:00:00',`lastdown`='0000-00-00 00:00:00',`prefixes`='0',`updown`='0';
