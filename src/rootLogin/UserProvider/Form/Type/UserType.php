<?php

namespace rootLogin\UserProvider\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\AbstractType;

class UserType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add("salutation","choice", array(
                'choices'  => array('f' => 'Female', 'm' => 'Male')
            ))
            ->add("prename","text", array(
                "constraints" => array(new Assert\NotBlank())
            ))
            ->add("lastname","text", array(
                "constraints" => array(new Assert\NotBlank())
            ))
            ->add("email","email", array(
                "constraints" => array(new Assert\NotBlank(), new Assert\Email(array(
                    "strict" => true,
                    "checkMX" => true
                ))),
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'rootLogin\UserProvider\Entity\User',
        ));
    }

    public function getName()
    {
        return 'user';
    }
}
