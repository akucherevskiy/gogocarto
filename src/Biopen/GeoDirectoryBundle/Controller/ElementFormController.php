<?php

/**  
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2017-11-08 16:09:12
 */
 

namespace Biopen\GeoDirectoryBundle\Controller;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Biopen\GeoDirectoryBundle\Document\Element;
use Biopen\GeoDirectoryBundle\Document\ElementStatus;
use Biopen\GeoDirectoryBundle\Form\ElementType;

use Symfony\Component\Form\Extension\Core\Type\EmailType;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use joshtronic\LoremIpsum;

class ElementFormController extends Controller
{
	public function addAction(Request $request)
	{
		$em = $this->get('doctrine_mongodb')->getManager();

		return $this->renderForm(new Element(), false, $request, $em);	
  	} 

	public function editAction($id, Request $request)
	{		
		$em = $this->get('doctrine_mongodb')->getManager();

		$element = $em->getRepository('BiopenGeoDirectoryBundle:Element')->find($id);

		if ($element->getStatus() <= ElementStatus::PendingAdd && !$this->container->get('biopen.config_service')->isUserAllowed('directModeration'))
		{
			$request->getSession()->getFlashBag()->add('error', "Désolé, vous n'êtes pas autorisé à modifier cet élement !");
			return $this->redirect($this->generateUrl('biopen_directory'));
		}
		else
		{
			return $this->renderForm($element, true, $request, $em);		
		}		
	}	

	// render for both Add and Edit actions
	private function renderForm($element, $editMode, $request, $em)
	{
		if (null === $element) {
		  throw new NotFoundHttpException("Cet élément n'existe pas.");
		}

		$addOrEditComplete = false;

		$securityContext = $this->container->get('security.context');
		$session = $this->getRequest()->getSession();
		$configService = $this->container->get('biopen.config_service');
		$addEditName = $editMode ? 'edit' : 'add';

		$isAllowedDirectModeration = $configService->isUserAllowed('directModeration');

		if ($request->get('logout')) $session->remove('user_email');

		// is user not allowed, we show the contributor-login page
		if (!$configService->isUserAllowed($addEditName, $request, $session->get('user_email')))
		{
			// creating simple form to let user enter a email address
			$loginform = $this->get('form.factory')->createNamedBuilder('user', 'form')
			->add('email', 'email', array('required' => false))
			->getForm();			

			if ($loginform->handleRequest($request)->isValid()) 
			{
				$user = $request->request->get('user')['email'];
				$user_email = $user;
				
				$session->set('user_email', $user_email);
			}
			else
			{
				return $this->render('@BiopenGeoDirectory/element-form/contributor-login.html.twig', array(
					'loginForm' => $loginform->createView(),
					'featureConfig' => $configService->getFeatureConfig($addEditName)));
			}		   
		} 
		// depending on authentification type (account or just giving email) we fill some variables
		else 
		{
			if ($securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED'))
			{
				$user = $this->get('security.context')->getToken()->getUser();
				$user_email = $user->getEmail();
			}
			else if ($session->has('user_email'))
			{
				$user = $session->get('user_email');
				$user_email = $session->get('user_email');
			}
			else
			{
				$user = 'Anonymous';
				$user_email = 'Anonymous';
			}
		}		
		
		// create the element form
		$elementForm = $this->get('form.factory')->create(ElementType::class, $element);

		// when we check for duplicates, we jump to an other action, and coem back to the add action
		// with the "duplicate" GET param to true. We check that in this case an 'elementWaitingForDuplicateCheckForDuplicateCheck'
		// is stored in the session
		$checkDuplicateOk = $request->query->get('checkDuplicate') && $session->has('elementWaitingForDuplicateCheck');

		//  If form submitted with valid values
		if ($elementForm->handleRequest($request)->isValid() || $checkDuplicateOk) 
		{	
			// if checkDuplicate process is done
			if ($checkDuplicateOk)
			{			
				$element = $session->get('elementWaitingForDuplicateCheck');
				$em->persist($element);
				$em->flush();
				// filling the form with the previous element created in case we want to recopy its informations (only for admins)
				$elementForm = $this->get('form.factory')->create(ElementType::class, $element);				
			}
			// if we just submit the form
			else
			{				
				// check for duplicates in Add action
				if (!$editMode)
				{					
					$duplicates = $this->get("biopen.element_form_service")->checkForDuplicates($element);
					$needToCheckDuplicates = count($duplicates) > 0;
				}
				else $needToCheckDuplicates = false;

				// custom handling form (to creating OptionValues for example)
				$element = $this->get("biopen.element_form_service")->handleFormSubmission($element, $request, $editMode, $user_email);	

				if ($needToCheckDuplicates)	
				{				
					// saving values in session instead of querying in the DB them again (don't know what's the best)
					$session->set('elementWaitingForDuplicateCheck', $element);
					$session->set('duplicatesElements', $duplicates);	
					$session->set('recopyInfo', $request->request->get('recopyInfo'));
					$session->set('sendMail', $request->request->get('send_mail'));
					// redirect to check duplicate
					return $this->redirect($this->generateUrl('biopen_element_check_duplicate'));			
				}
				else 
				{
					$em->persist($element);
					$em->flush();
				}			
			}
			
			$sendMail = $request->request->has('send_mail') ? $request->request->get('send_mail') : $session->get('sendMail');

			// Unless admin ask for not sending mails
			if ($isAllowedDirectModeration && $sendMail)
			{
				$mailService = $this->container->get('biopen.mail_service');
            $mailService->sendAutomatedMail($editMode ? 'edit' : 'add', $element);
			}			

			// Add flashBags succeess
			$url_new_element = str_replace('%23', '#', $this->generateUrl('biopen_directory_showElement', array('name' => $element->getName(), 'id'=>$element->getId())));				

			$noticeText = 'Merci de votre contribution ! ';
			if ($editMode) $noticeText .= 'Les modifications ont bien été prises en compte';
			else $noticeText .=  ucwords($configService->getConfig()->getElementDisplayNameDefinite()) . " a bien été ajouté :)";

			if ($element->isPending())
			{
				$noticeText .= "</br>Il est pour l'instant en attente de validation, <a class='validation-process' onclick=\"$('#popup-collaborative-explanation').openModal()\">cliquez ici</a> pour en savoir plus sur le processus de modération collaborative !";
			}

			$submitOption = $request->request->get('submit-option');
			$isAllowedPending = $configService->isUserAllowed('pending');

			$showResultLink = $submitOption == 'stayonform' && ($isAllowedDirectModeration || $isAllowedPending);
			if ($showResultLink) $noticeText .= '</br><a href="' . $url_new_element . '">Voir le résultat sur la carte</a>';

			$request->getSession()->getFlashBag()->add('success', $noticeText);			

			// getting the admin option "recopy info" from POST or from session (in case of checkDuplicate process)
			$recopyInfo = $request->request->has('recopyInfo') ? $request->request->get('recopyInfo') : $session->get('recopyInfo');

			// clear session
			$session->remove('elementWaitingForDuplicateCheck');
			$session->remove('duplicatesElements');
			$session->remove('recopyInfo');
			$session->remove('send_mail');

			if ($submitOption != 'stayonform' && !$recopyInfo) return $this->redirect($url_new_element);	

			// Unless admin ask for recopying the informations
			if (!($isAllowedDirectModeration && $recopyInfo))
			{
				// resetting form
				$editMode = false;
				$elementForm = $this->get('form.factory')->create(ElementType::class, new Element());
				$element = new Element();
			}			

			$addOrEditComplete = true;			
		}

		if (!$securityContext->isGranted('IS_AUTHENTICATED_REMEMBERED') && !$session->has('user_email') && !$addOrEditComplete) 
		{		
			$flashMessage = "Vous êtes actuellement en mode \"Anonyme\"</br> Connectez-vous pour augmenter notre confiance dans vos contributions !";
			$request->getSession()->getFlashBag()->add('notice', $flashMessage);
		}	
		// else if ($session->has('user_email') && !$addOrEditComplete)	
		// {
		// 	$flashMessage = 'Vous êtes identifié en tant que "' . $user .'"</br><a onclick="logout()" href="?logout=1">Changer d\'utilisateur</a>';
		// 	$request->getSession()->getFlashBag()->add('notice', $flashMessage);
		// }

		// retrieve mainCategory as Json and unserialized it because if no it need a lot of query to
		// retrieve all the taxonomy tree
		$mainCategoryJson = $em->getRepository('BiopenGeoDirectoryBundle:Taxonomy')
		->findMainCategoryJson();

 		$mainCategory = json_decode($mainCategoryJson);

		return $this->render('@BiopenGeoDirectory/element-form/element-form.html.twig', 
					array(
						'editMode' => $editMode,
						'form' => $elementForm->createView(),
						'mainCategory'=> $mainCategory,
						"element" => $element,
						"user_email" => $user_email,
						"isAllowedDirectModeration" => $isAllowedDirectModeration,
						"config" => $configService->getConfig()
					));
	}

	// when submitting new element, check it's not yet existing
	public function checkDuplicatesAction(Request $request)
	{
		$em = $this->get('doctrine_mongodb')->getManager();
		$session = $this->getRequest()->getSession();

		// a form with just a submit button
		$checkDuplicatesForm = $this->get('form.factory')->createNamedBuilder('duplicates', 'form')->getForm();	

		if ($checkDuplicatesForm->handleRequest($request)->isValid()) 
		{
			// if user say that it's not a duplicate, we go back to add action with checkDuplicate to true
			return $this->redirect($this->generateUrl('biopen_element_add', array('checkDuplicate' => true)));
		}
		// check that duplicateselement are in session and are not empty
		else if ($session->has('duplicatesElements') && count($session->get('duplicatesElements') > 0))
		{
			$duplicates = $session->get('duplicatesElements');
			// c'est aucun d'eux, je continue
			// c'est lui -> redirige vers showElement 
			return $this->render('@BiopenGeoDirectory/element-form/check-for-duplicates.html.twig', array('duplicateForm' => $checkDuplicatesForm->createView(), 
																															 'duplicatesElements' => $duplicates));
		}	
		// otherwise just redirect ot add action
		else 
		{
			return $this->redirect($this->generateUrl('biopen_element_add'));
		}			
	}	
}
