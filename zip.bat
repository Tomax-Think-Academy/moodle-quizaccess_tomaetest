del TET-accessrule-moodle.zip
mkdir tomaetest
copy * tomaetest
Xcopy  /S /I /E classes  tomaetest\classes
Xcopy  /S /I /E db  tomaetest\db
Xcopy  /S /I /E lang  tomaetest\lang
rmdir tomaetest\tomaetest /s /q
tar.exe -a -c -f TET-accessrule-moodle.zip tomaetest
rmdir tomaetest /s /q