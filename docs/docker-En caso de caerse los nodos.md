Si se apaga uno de los nodos del Docker, el sitio **puede seguir funcionando**, pero depende de qué rol tenía ese nodo y de si el resto del clúster sigue atendiendo las rutas críticas [web:904][web:906].

## Qué pasa normalmente
En un entorno multi-nodo, la idea es que los contenedores y servicios puedan seguir sirviendo tráfico desde otros nodos si uno cae [web:904][web:906].
Si el nodo caído no era el único que tenía un servicio necesario, la aplicación sigue arriba con degradación parcial o sin que el usuario lo note demasiado [web:906].

## En SisDist
Como SisDist separa matriz y sucursales, si cae un nodo de una sucursal, el sistema puede seguir atendiendo otras sucursales y la parte global del catálogo si esos servicios siguen disponibles [file:328].
Pero las operaciones de ese nodo caído, como stock o pedidos de esa sucursal, pueden quedar temporalmente no disponibles hasta recuperar el contenedor o el servicio [file:328].

## Cuándo sí se cae el sitio
Si el nodo que cae contiene un servicio único e indispensable para la app, o si el despliegue no tiene réplicas/redistribución, entonces una parte del sitio o incluso todo el sitio puede fallar [web:906][web:910].
También puede fallar si el punto de entrada, el balanceador o la base de datos crítica estaban solo en ese nodo [web:906].

## Resumen práctico
- Si cae un nodo y hay otros nodos con el servicio, el sitio sigue funcionando o lo hace en modo degradado [web:904][web:906].
- Si cae el único nodo que tenía ese servicio, la funcionalidad ligada a ese nodo deja de responder [web:910].
- En SisDist, lo esperable es que el sitio siga vivo para lecturas o módulos no afectados, pero no necesariamente para escribir en la sucursal caída [file:328].

## Conclusión
La respuesta corta es: **sí, puede seguir funcionando**, pero con pérdida parcial de funcionalidades. En este proyecto, la continuidad depende de que existan otros nodos o réplicas atendiendo, y de que el servicio caído no sea el único punto crítico [web:904][web:906][file:328].