# moodle-gradeexport_xlsskema 
xls export with skema format

RULES :
The export for Skema must indicate for each students all the groups he's enrolled in for that course, excepted the manual groups. The group idnumber column will correspond to the 4 last digits of the groupID. If a student belongs to several groups for a course, they must all be present, separated by a comma. Same thing for the group names. 

Scenario:
Given a course with 3 groups in Common Resources
    And group1 was created by the synchro
    And group2 course was created by the synchro
    And manual group was created manually (no id number)
    And student A is in group1 only
    And student B is in group2 only
    And student C is in manual group only
    And student D is in group1 and group2
    And student E is in group1 and manual group
    And student F is in group 1 and group2 and manual group
    And student G is in no group
When the user selects Export as Excel spreadsheet for Skema 
    And selects all participants 
    And selects all the grades
    And clicks on Download
Then an excel sheet is downloaded with 3 additionnal colums: Course shortname, Group name and Group idnumber
    And for student A there is group1
    And for student B there is group2
    And for student C there is nothing (no groups)
    And for student D there is group1, group2
    And for student E there is group1
    And for student F there is group1, group2
    And for student G there is nothing (no groups)