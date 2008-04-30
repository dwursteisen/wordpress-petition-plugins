all:	fcpetition-he_IL.mo fcpetition.pot fcpetition-nl_NL.mo

fcpetition.pot:	fcpetition.php
	xgettext --language=PHP --indent --keyword=__ --keyword=_e --keyword=__ngettext:1,2 -s -n --from-code=UTF8 -o fcpetition.pot fcpetition.php

fcpetition-he_IL.mo:	fcpetition-he_IL.po
	msgfmt -o fcpetition-he_IL.mo fcpetition-he_IL.po

fcpetition-nl_NL.mo:	fcpetition-nl_NL.po
	msgfmt -o fcpetition-nl_NL.mo fcpetition-nl_NL.po

