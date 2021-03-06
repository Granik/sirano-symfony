<?php

namespace App\Backend\Form;


use App\Domain\Backend\Interactor\ArticleInteractor;
use App\Domain\Backend\Interactor\DirectionInteractor;
use App\Domain\Entity\Article\Article;
use App\Domain\Entity\Direction\Category;
use App\Domain\Entity\Module\Backend\DTO\ModuleDto;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ModuleType extends WithDirectionType
{
    /**
     * @var ArticleInteractor
     */
    private $articleInteractor;
    
    /**
     * ModuleArticleType constructor.
     *
     * @param ArticleInteractor $articleInteractor
     */
    public function __construct(DirectionInteractor $directionInteractor, ArticleInteractor $articleInteractor)
    {
        parent::__construct($directionInteractor);
        
        $this->articleInteractor = $articleInteractor;
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var ModuleDto|null $moduleDto */
        $moduleDto = $options['data'] ?? null;
        $direction = $moduleDto ? $moduleDto->direction : null;
        
        $builder
            ->add(
                'name',
                TextType::class,
                [
                    'constraints' => new NotBlank([
                        'groups' => ['update'],
                    ]),
                    'label'       => 'Название',
                ]
            )
            ->add(
                'direction',
                ChoiceType::class, [
                    'choices'     => $this->getDirectionChoices(),
                    'constraints' => new NotBlank([
                        'groups' => ['update'],
                    ]),
                    'label'       => 'Направление',
                ]
            )
            ->add(
                'category',
                ChoiceType::class, [
                    'choices'  => $this->getCategoryChoices($direction),
                    'required' => false,
                    'label'    => 'Категория',
                ]
            )
            ->add(
                'number',
                IntegerType::class,
                [
                    'constraints' => [
                        new NotBlank(['groups' => ['update']]),
                        new GreaterThanOrEqual([
                            'groups' => ['update'],
                            'value'  => 1
                        ])],
                    'label'       => 'Порядковый номер',
                ]
            )
            ->add(
                'youtubeCode',
                TextareaType::class,
                [
                    'required' => false,
                    'label'    => 'Код видео (youtube)',
                ]
            )
            ->add(
                'isActive',
                CheckboxType::class,
                [
                    'label'    => 'Активно',
                    'required' => false,
                ]
            )
            ->add(
                'slides',
                CollectionType::class, [
                    'label'         => false,
                    'entry_type'    => ModuleSlideType::class,
                    'entry_options' => ['label' => false, 'validation_groups' => ['update']],
                    'allow_add'     => true,
                    'allow_delete'  => true,
                ]
            )
            ->add(
                'articles',
                CollectionType::class, [
                    'label'         => false,
                    'entry_type'    => ChoiceType::class,
                    'entry_options' => [
                        'label'   => false,
                        'choices' => $this->getArticleChoices(),
                        'attr'    => ['class' => 'select2'],
                    ],
                    'allow_add'     => true,
                    'allow_delete'  => true,
                ]
            )
            ->add('Сохранить', SubmitType::class);
        
        $builder->get('direction')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                $form = $event->getForm();
                $this->setupCategoryField($form->getParent(), $form->getData());
            }
        );
        
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                /** @var ModuleDto|null $data */
                $data = $event->getData();
                
                if (!$data) {
                    return;
                }
                
                $this->setupCategoryField($event->getForm(), $data->direction);
            }
        );
    }
    
    private function getArticleChoices()
    {
        $choices = [];
        
        /** @var Article $article */
        foreach ($this->articleInteractor->listAll() as $article) {
            $choices[$article->getName()] = $article->getId();
        }
        
        return $choices;
    }
    
    /**
     * @param $direction
     *
     * @return array
     * @throws \App\Interactors\NonExistentEntity
     */
    private function getCategoryChoices($direction)
    {
        $choices = [];
        
        if ($direction === null) {
            return $choices;
        }
        
        /** @var Category $category */
        foreach ($this->directionInteractor->listCategoryByDirection($direction) as $category) {
            $choices[$category->getName()] = $category->getId();
        }
        
        return $choices;
    }
    
    private function setupCategoryField(FormInterface $form, $direction)
    {
        if ($direction === null) {
            $form->remove('category');
            
            return;
        }
        
        $choices = $this->getCategoryChoices($direction);
        
        if (empty($choices)) {
            $form->remove('category');
            
            return;
        }
        
        $form->add(
            'category',
            ChoiceType::class, [
                'choices'  => $choices,
                'required' => false,
                'label'    => 'Категория',
            ]
        );
    }
}