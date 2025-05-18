<?php
/**
 * @noinspection PhpMultipleClassesDeclarationsInOneFile
 * @noinspection PhpMultipleClassDeclarationsInspection
 * @noinspection PhpIllegalPsrClassPathInspection
 */

namespace SV\ReportImprovements\XF\Entity
{
	class XFCP_Search extends \SV\SearchImprovements\XF\Entity\Search {}
}

namespace SV\ReportImprovements\XF\Service\User
{
    class XFCP_Warn extends \SV\ForumBan\XF\Service\User\Warn {}
}

namespace XF\Finder
{
    class ApprovalQueue extends \XF\Mvc\Entity\Finder {}
}
