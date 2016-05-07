<?php

namespace Caxy\BadgeKit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ServiceClient.
 *
 * @method ResultInterface getSystems(array $args) get systems
 * @method ResultInterface getSystem(array $args) get system
 * @method ResultInterface createSystem(array $args) create system
 * @method ResultInterface updateSystem(array $args) update system
 * @method ResultInterface deleteSystem(array $args) delete system
 * @method ResultInterface getIssuers(array $args) get issuers
 * @method ResultInterface getIssuer(array $args) get issuer
 * @method ResultInterface createIssuer(array $args) create issuer
 * @method ResultInterface updateIssuer(array $args) update issuer
 * @method ResultInterface deleteIssuer(array $args) delete issuer
 * @method ResultInterface getPrograms(array $args) get programs
 * @method ResultInterface getProgram(array $args) get program
 * @method ResultInterface createProgram(array $args) create program
 * @method ResultInterface updateProgram(array $args) update program
 * @method ResultInterface deleteProgram(array $args) delete program
 * @method ResultInterface getAssertion(array $args) get assertion
 * @method ResultInterface getBadges(array $args) get badges
 * @method ResultInterface getBadge(array $args) get badge
 * @method ResultInterface createBadge(array $args) create badge
 * @method ResultInterface updateBadge(array $args) update badge
 * @method ResultInterface deleteBadge(array $args) delete badge
 * @method ResultInterface getBadgeFromClaimCode(array $args)
 * @method ResultInterface getClaimCode(array $args) get claim code
 * @method ResultInterface getClaimCodes(array $args) get claim codes
 * @method ResultInterface createClaimCode(array $args) create claim code
 * @method ResultInterface deleteClaimCode(array $args) delete claim code
 * @method ResultInterface claimCode(array $args) claim code
 * @method ResultInterface createRandomCode(array $args) create random code
 * @method ResultInterface getUserBadgeInstances(array $args) get user badge instances
 * @method ResultInterface getBadgeInstance(array $args) get badge instance
 * @method ResultInterface getBadgeInstances(array $args) get badge instances
 * @method ResultInterface createBadgeInstance(array $args) create badge instance
 * @method ResultInterface deleteBadgeInstance(array $args) delete badge instance
 * @method ResultInterface getAdminApplications(array $args) get admin applications
 * @method ResultInterface getApplications(array $args) get applications
 * @method ResultInterface getApplication(array $args) get application
 * @method ResultInterface createApplication(array $args) create application
 * @method ResultInterface updateApplication(array $args) update application
 * @method ResultInterface deleteApplication(array $args) delete application
 * @method ResultInterface getReviews(array $args) get reviews
 * @method ResultInterface getReview(array $args) get review
 * @method ResultInterface createReview(array $args) create review
 * @method ResultInterface updateReview(array $args) update review
 * @method ResultInterface deleteReview(array $args) delete review
 * @method ResultInterface getMilestones(array $args) get milestones
 * @method ResultInterface getMilestone(array $args) get milestone
 * @method ResultInterface createMilestone(array $args) create milestone
 * @method ResultInterface updateMilestone(array $args) update milestone
 * @method ResultInterface deleteMilestone(array $args) delete milestone
 * @method ResultInterface addBadgeToMilestone(array $args) add badge to milestone
 * @method ResultInterface removeBadgeFromMilestone(array $args) remove badge from milestone
 */
class ServiceClient extends \GuzzleHttp\Command\ServiceClient
{
    /**
     * @var array
     */
    private $api;

    /**
     * BadgeKitClient constructor.
     *
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->api = json_decode(file_get_contents(__DIR__.'/../res/badgekit.json'), true);
        parent::__construct($client, [$this, 'commandToRequestTransformer'], [$this, 'responseToResultTransformer']);
    }

    /**
     * @param CommandInterface $command
     *
     * @return RequestInterface
     */
    public function commandToRequestTransformer(CommandInterface $command)
    {
        $name = $command->getName();
        if (!isset($this->api[$name])) {
            throw new CommandException('Command not found', $command);
        }
        $action = $this->api[$name];

        $prefix = '';
        if (isset($action['public']) && $command->hasParam('public')) {
            $prefix = '/public';
        }

        $prefixes = [
            'system' => '/systems/{system}',
            'issuer' => '/issuers/{issuer}',
            'program' => '/programs/{program}',
        ];
        if (isset($action['admin_contexts'])) {
            $prefix .= implode('', array_intersect_key($prefixes, array_flip($this->api[$name]['admin_contexts']), $command->toArray()));
        }
        $path = \GuzzleHttp\uri_template($prefix.$action['path'], $command->toArray());

        $headers = [];
        $body = null;
        if ($command->hasParam('body')) {
            $headers = ['Content-Type' => 'application/json'];
            $body = \GuzzleHttp\json_encode($command['body']);
        }

        return new Psr7\Request($action['method'], $path, $headers, $body);
    }

    /**
     * @param ResponseInterface $response
     * @param RequestInterface  $request
     *
     * @return Result
     */
    public function responseToResultTransformer(ResponseInterface $response, RequestInterface $request)
    {
        $data = json_decode($response->getBody(), true);

        return new Result($data);
    }
}
