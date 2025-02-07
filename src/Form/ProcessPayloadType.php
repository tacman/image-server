<?php

namespace App\Form;

use Survos\SaisBundle\Model\ProcessPayload;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProcessPayloadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('images', TextareaType::class, [
                'attr' => [
                    'cols' => 80,
                    'rows' => 5,
                ]

            ])
            ->add('filters', TextareaType::class, [
                'attr' => [
                    'cols' => 10,
                    'rows' => 3,
                ]

            ])
            ->add('callbackUrl', UrlType::class, [
                'default_protocol' => 'https',
                'required' => false,
            ])
        ;

        $builder->get('images')
            ->addModelTransformer(new CallbackTransformer(
                fn ($tagsAsArray): string => implode("\n", $tagsAsArray),
                fn ($tagsAsString): array => array_map('trim', explode("\n", $tagsAsString)),
            ))
        ;

        $builder->get('filters')
            ->addModelTransformer(new CallbackTransformer(
                fn ($tagsAsArray): string => implode("\n", $tagsAsArray),
                fn ($tagsAsString): array => array_map('trim', explode("\n", $tagsAsString)),
            ))
        ;

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProcessPayload::class,
        ]);
    }
}
