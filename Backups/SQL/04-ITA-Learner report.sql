SELECT DISTINCT
CONCAT(u.firstname, ' ',u.lastname) AS name,
CONCAT('<span class="accesshide" >', CAST(u.timecreated as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(u.timecreated),'%d %M %Y')) AS 'Account created',
IF (u.lastaccess = 0,'<span class="accesshide" >000000000000</span>never', CONCAT('<span class="accesshide" >', CAST(u.lastaccess as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(u.lastaccess),'%d %M %Y'))) AS 'Last logged on',
prog.fullname AS 'Programme',
CONCAT('<a target="_new" href="%%WWWROOT%%/report/log/user.php?id=',u.id,'&course=1&mode=all">Log</a>') AS 'Activity Log',
CONCAT('<a target="_new" href="%%WWWROOT%%/report/completion/user.php?id=',u.id,'&course=1">Course progress</a>') AS 'Course progress'

FROM prefix_role_assignments AS ra
JOIN prefix_context AS context ON context.id = ra.contextid
JOIN prefix_user u ON u.id = context.instanceid
LEFT JOIN prefix_prog_user_assignment AS progua ON progua.userid = u.id 
LEFT JOIN prefix_prog AS prog ON prog.id = progua.programid
JOIN prefix_role AS role ON role.id = ra.roleid
LEFT Join prefix_user_info_data uid ON uid.userid = u.id
LEFT JOIN prefix_user_info_field uif ON uid.fieldid = uif.id
LEFT JOIN prefix_prog_info_data pid ON pid.programid = prog.id
LEFT JOIN prefix_prog_info_field pif on pid.fieldid = pif.id


WHERE prog.fullname IS NOT NULL
AND ra.userid = %%USERID%%
AND context.contextlevel = 30
AND u.suspended = 0
AND (uif.shortname IS null OR (uif.shortname = 'hidefromITA' AND uid.data != 1))
AND (pif.shortname = 'showITAdashboard' AND pid.data = 1)

ORDER BY u.firstname