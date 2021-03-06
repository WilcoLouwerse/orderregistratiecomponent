<?php

namespace App\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Order;
use App\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

//use App\Entity\Request as CCRequest;

class OrderSubscriber implements EventSubscriberInterface
{
    private $params;
    private $em;
    private $serializer;
    private $nlxLogService;

    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, SerializerInterface $serializer)
    {
        $this->params = $params;
        $this->em = $em;
        $this->serializer = $serializer;
    }

    public static function getSubscribedEvents()
    {
        return [
                KernelEvents::VIEW => ['newOrder', EventPriorities::PRE_VALIDATE],
        ];
    }

    public function newOrder(ViewEvent $event)
    {
    	$result = $event->getControllerResult();
    	$method = $event->getRequest()->getMethod();
    	$route = $event->getRequest()->attributes->get('_route');

    	if(!$result instanceof Order || $route != 'api_orders_post_collection'){
    		//var_dump('a');
    		return;
    	}

    	if(!$result->getReference()){
    		$organisation = $result->getOrganization();

    		if(!$organisation || !($organisation instanceof Organization)){
    			$organisation = $this->em->getRepository('App\Entity\Organization')->findOrCreateByRsin($result->getTargetOrganization());
    			$this->em->persist($organisation);
    			$this->em->flush();
    			$result->setOrganization($organisation);
    		}

    		$referenceId = $this->em->getRepository('App\Entity\Order')->getNextReferenceId($organisation);
    		$result->setReferenceId($referenceId);
    		$result->setReference($organisation->getShortCode().'-'.date('Y').'-'.$referenceId);
    	}

        return $result;
    }

}
