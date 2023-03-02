SELECT
DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish),'%d %M %Y') AS Date,
DATE_FORMAT(FROM_UNIXTIME(attempts.timefinish),'%d/%m/%Y') AS Date2,
Count(attempts.id) AS Submitted

FROM prefix_quiz_attempts attempts
JOIN prefix_quiz quiz ON attempts.quiz=quiz.id
JOIN prefix_user u ON u.id = attempts.userid
JOIN prefix_course_modules cm ON cm.instance=quiz.id and cm.module = 18
JOIN prefix_course c ON c.id = cm.course
JOIN prefix_course_categories AS cc ON cc.id = c.category 

WHERE 
quiz.preferredbehaviour = 'deferredfeedback'
AND u.suspended = 0
/*AND cc.name = 'NZ Cert CR and AR 2017'*/
/*Admin users, Demo learenrs and test users are hidden from this queue*/
AND u.username != 'elearning.admin'  AND u.username != 'mito1' AND u.username != 'mito2' AND u.username != 'mito_nzcr' AND u.username != 'mito_nzar' AND u.username != 'mito1' AND u.username != 'mito_nzlv' AND u.username != 'mito_suig'
AND u.username != '773294@mymitonz.org.nz' AND u.username != '773288@mymitonz.org.nz' AND u.username != '773290@mymitonz.org.nz' AND u.username != 'demolearner'
AND u.username != 'gbalasuriya' AND u.username != 'hclark' AND u.username != 'kahmad' 
/**/
GROUP BY Date

ORDER BY attempts.timefinish DESC