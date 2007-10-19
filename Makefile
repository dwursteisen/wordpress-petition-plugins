all:	pot mo
	rm -f ../wordpress-petition.zip
	cd ..; zip wordpress-petition.zip petition/fcpetition* petition/gpl-2.0.txt petition/readme.txt; cd petition;

pot:
	xgettext --language=PHP --indent --keyword=__ --keyword=_e --keyword=__ngettext:1,2 -s -n --from-code=UTF8 -o fcpetition.pot fcpetition.php
mo:
	msgfmt -o fcpetition-nl_NL.mo fcpetition-nl_NL.po
	msgfmt -o fcpetition-it_IT.mo fcpetition-it_IT.po
	msgfmt -o fcpetition-zh_CN.mo fcpetition-zh_CN.po
