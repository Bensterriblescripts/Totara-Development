SELECT
u.username username, 
l.userid AS  userId, 
CONCAT(u.firstname, ' ', u.lastname) AS Assessor,
COUNT(l.id) 'Questions graded',
DATE_FORMAT(FROM_UNIXTIME(l.timecreated),'%d %M %Y') AS Day,
DATE_FORMAT(FROM_UNIXTIME(l.timecreated),'%d/%m/%y') AS DayAlternate

FROM 
prefix_logstore_standard_log l
JOIN prefix_user u ON u.id = l.userid


WHERE 
(u.username in ('kburke', 'iclarke', 'rlunn') or l.userid in (12821,13738,6167,6641,10915,9917,9390,5025,6390,7032,6765,4081,9429,9543,16722,20640,11407,15560,6838,19288,7375,9674,6136,9618)) 
 
AND l.action = 'graded'
AND l.target = 'question_manually'
AND ROUND((UNIX_TIMESTAMP() - l.timecreated)/86400,0) < 365
GROUP BY Day, u.username

%%FILTER_SEARCHTEXT:(Concat(u.firstname, ' ', u.lastname)):~%%

/*%%FILTER_STARTTIME:l.timecreated:>%% %%FILTER_ENDTIME:l.timecreated:<%%*/
ORDER BY l.timecreated DESC

