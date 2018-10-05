<?php declare(strict_types=1);

namespace Weebly\PHPStan\Laravel;

use Illuminate\Auth\AuthManager;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use PHPStan\Analyser\NameScope;
use PHPStan\Broker\Broker;
use PHPStan\PhpDoc\PhpDocStringResolver;
use PHPStan\Reflection\Annotations\AnnotationMethodReflection;
use PHPStan\Reflection\Annotations\AnnotationsMethodParameterReflection;
use PHPStan\Reflection\BrokerAwareExtension;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\MethodReflection;

final class FacadeMethodExtension implements MethodsClassReflectionExtension, BrokerAwareExtension
{
    /**
     * @var \PHPStan\Broker\Broker
     */
    private $broker;

    /**
     * @var string[]
     */
    private $extensions = [
        AuthManager::class => 'guard',
        BroadcastManager::class => 'driver',
    ];

    /**
     * @var \PHPStan\Reflection\MethodReflection[]
     */
    private $methods = [];

    /**
     * @var \Weebly\PHPStan\Laravel\MethodReflectionFactory
     */
    private $methodReflectionFactory;

    /**
     * @var \PHPStan\PhpDoc\PhpDocStringResolver
     */
    private $phpDocStringResolver;

    /**
     * FacadeMethodExtension constructor.
     *
     * @param \Weebly\PHPStan\Laravel\MethodReflectionFactory $methodReflectionFactory
     * @param \PHPStan\PhpDoc\PhpDocStringResolver $phpDocStringResolver
     */
    public function __construct(
        MethodReflectionFactory $methodReflectionFactory, PhpDocStringResolver $phpDocStringResolver
    ) {
        $this->methodReflectionFactory = $methodReflectionFactory;
        $this->phpDocStringResolver = $phpDocStringResolver;
    }

    /**
     * @inheritdoc
     */
    public function setBroker(Broker $broker): void
    {
        $this->broker = $broker;
    }

    /**
     * @inheritdoc
     */
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if ($classReflection->isSubclassOf(Facade::class)) {
            if (!isset($this->methods[$classReflection->getName()])) {
                /** @var \Illuminate\Support\Facades\Facade $class */
                $class = $classReflection->getName();
                $instance = $class::getFacadeRoot();

                $instanceReflection = $this->broker->getClass(get_class($instance));
                $this->methods[$classReflection->getName()] = $this->createMethods(
                    $classReflection,
                    $instanceReflection
                );

                if (isset($this->extensions[$instanceReflection->getName()])) {
                    $extensionMethod = $this->extensions[$instanceReflection->getName()];
                    $extensionReflection = $this->broker->getClass(get_class($instance->$extensionMethod()));
                    $this->methods[$classReflection->getName()] += $this->createMethods(
                        $classReflection,
                        $extensionReflection
                    );
                }
            }
        }

        return isset($this->methods[$classReflection->getName()][$methodName]);
    }

    /**
     * @inheritdoc
     */
    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return $this->methods[$classReflection->getName()][$methodName];
    }

    /**
     * @param \PHPStan\Reflection\ClassReflection $classReflection
     * @param \PHPStan\Reflection\ClassReflection $instance
     *
     * @return \PHPStan\Reflection\MethodReflection[]
     * @throws \PHPStan\ShouldNotHappenException
     */
    private function createMethods(ClassReflection $classReflection, ClassReflection $instance): array
    {
        $methods = [];

        $docBlock = $classReflection->getNativeReflection()->getDocComment();

        if ($docBlock !== false) {
            $nameScope = new NameScope($classReflection->getNativeReflection()->getNamespaceName());
            $doc = $this->phpDocStringResolver->resolve(
                $docBlock,
                $nameScope
            );
            // TODO: Find a way to not copy-paste this from
            // \PHPStan\Reflection\Annotations\AnnotationsMethodsClassReflectionExtension
            foreach ($doc->getMethodTags() as $methodName => $methodTag) {
                $parameters = [];
                foreach ($methodTag->getParameters() as $parameterName => $parameterTag) {
                    $parameters[] = new AnnotationsMethodParameterReflection(
                        $parameterName,
                        $parameterTag->getType(),
                        $parameterTag->isPassedByReference(),
                        $parameterTag->isOptional(),
                        $parameterTag->isVariadic()
                    );
                }

                $methods[$methodName] = new AnnotationMethodReflection(
                    $methodName,
                    $classReflection,
                    $methodTag->getReturnType(),
                    $parameters,
                    $methodTag->isStatic(),
                    $this->detectMethodVariadic($parameters)
                );
            }
        }

        foreach ($instance->getNativeReflection()->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // Trust annotated methods
            if (isset($methods[$method->getName()])) {
                continue;
            }
            $methods[$method->getName()] = $this->methodReflectionFactory->create(
                $classReflection,
                $method,
                ReflectionMethodAlwaysStatic::class
            );
        }

        return $methods;
    }

    /**
     * Detect if last parameter is variadic
     * @see \PHPStan\Reflection\Annotations\AnnotationsMethodsClassReflectionExtension
     * @param AnnotationsMethodParameterReflection[] $parameters
     * @return bool
     */
    private function detectMethodVariadic(array $parameters): bool
    {
        if ($parameters === []) {
            return false;
        }

        $possibleVariadicParameterIndex = count($parameters) - 1;
        $possibleVariadicParameter = $parameters[$possibleVariadicParameterIndex];

        return $possibleVariadicParameter->isVariadic();
    }
}
