<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;

/**
 *
 */
class DefaultController extends \TeiEditionBundle\Controller\TopicController
{
    /* shared code with PlaceController */
    use \TeiEditionBundle\Controller\MapHelperTrait;

    /**
     * @Route("/", name="home")
     */
    public function indexAction(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        ?FeatureManager $featureManager = null
    ) {
        [$markers, $bounds] = $this->buildMap($entityManager, $request->getLocale());

        $news = [];

        try {
            /* the following can fail */
            $url = $this->getParameter('app.wp-rest.url');

            if (!empty($url)) {
                try {
                    $client = new \Vnn\WpApiClient\WpClient(
                        new \Vnn\WpApiClient\Http\GuzzleAdapter(new \GuzzleHttp\Client()),
                        $url
                    );
                    $client->setCredentials(new \Vnn\WpApiClient\Auth\WpBasicAuth($this->getParameter('app.wp-rest.user'), $this->getParameter('app.wp-rest.password')));
                    $posts = $client->posts()->get(null, [
                        'per_page' => 4,
                        'lang' => $request->getLocale(),
                    ]);

                    if (!empty($posts)) {
                        foreach ($posts as $post) {
                            $article = new \TeiEditionBundle\Entity\Article();
                            $article->setName($post['title']['rendered']);
                            $article->setSlug($post['slug']);
                            $article->setDatePublished(new \DateTime($post['date_gmt']));

                            $news[] = $article;
                        }
                    }
                }
                catch (\Exception $e) {
                    ;
                }
            }
        }
        catch (\InvalidArgumentException $e) {
            ; // ignore
        }

        return $this->render(
            $featureManager->isEnabled('limited_navigation')
                ? 'Default/home-limited.html.twig'
                : 'Default/home.html.twig',
            [
                'pageTitle' => $translator->trans('Welcome'),
                'genres' => KeywordController::fetchActiveKeywords($entityManager, $translator, $request->getLocale(), KeywordController::$GENRES, false),
                'topics' => $this->buildTopicsDescriptions($translator, $request->getLocale()),
                'markers' => $markers,
                'bounds' => $bounds,
                'news' => $news,
            ]
        );
    }
}
