SELECT
CONCAT(u.firstname, ' ',u.lastname) AS 'Name',
CONCAT('<span class="accesshide" >', CAST(progua.timeassigned as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(progua.timeassigned),'%d %M %Y')) AS 'Enrolled Date',
CASE
	WHEN u.lastaccess = 0 THEN 'never'
	ELSE CONCAT('<span class="accesshide" >', CAST(u.lastaccess as CHAR), '</span>', DATE_FORMAT(FROM_UNIXTIME(u.lastaccess),'%d %M %Y'))
END AS 'Last Access',
prog.fullname AS 'Programme',
CONCAT('<a target="_new" href="%%WWWROOT%%/report/log/user.php?id=',u.id,'&course=1&mode=all">Log</a>') AS 'Activity Log',
CONCAT('<a target="_new" href="%%WWWROOT%%/report/completion/user.php?id=',u.id,'&course=1">Course progress</a>') AS 'Course progress'

FROM prefix_role_assignments AS ra
JOIN prefix_context AS context 
    ON context.id = ra.contextid
LEFT JOIN prefix_role AS role 
    ON role.id = ra.roleid
JOIN prefix_prog_user_assignment AS progua 
    ON progua.userid = context.instanceid
LEFT JOIN prefix_user_info_data AS uid 
    ON uid.userid = progua.userid
LEFT JOIN prefix_user_info_field AS uif 
    ON uid.fieldid = uif.id
LEFT JOIN prefix_prog AS prog 
    ON prog.id = progua.programid
LEFT JOIN prefix_prog_info_data AS pid 
    ON pid.programid = progua.programid
LEFT JOIN prefix_prog_info_field AS pif 
    ON pid.fieldid = pif.id
JOIN prefix_user AS u
    ON u.id = progua.userid
JOIN
(
    SELECT
    MAX(timeassigned) AS enrolled,
	  u.id AS user
    FROM prefix_prog_user_assignment AS progua
	  JOIN prefix_user AS u
	  	ON u.id = progua.userid
    GROUP BY u.id
)   AS latest
        ON progua.userid = latest.user

WHERE prog.fullname IS NOT NULL
AND ra.userid = %%USERID%%
AND u.suspended = 0
AND latest.enrolled = progua.timeassigned

GROUP BY progua.id

ORDER BY u.firstname, u.lastname DESC