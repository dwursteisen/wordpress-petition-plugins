all:	fcpetition-nl_NL.mo fcpetition-it_IT.mo fcpetition-zh_CN.mo fcpetition.pot

fcpetition.pot:	fcpetition.php
	xgettext --language=PHP --indent --keyword=__ --keyword=_e --keyword=__ngettext:1,2 -s -n --from-code=UTF8 -o fcpetition.pot fcpetition.php

fcpetition-nl_NL.mo:	fcpetition-nl_NL.po
	msgfmt -o fcpetition-nl_NL.mo fcpetition-nl_NL.po
fcpetition-it_IT.mo:    fcpetition-it_IT.po
	msgfmt -o fcpetition-it_IT.mo fcpetition-it_IT.po
fcpetition-zh_CN.mo:    fcpetition-zh_CN.po
	msgfmt -o fcpetition-zh_CN.mo fcpetition-zh_CN.po
