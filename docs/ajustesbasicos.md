# Checkpoint: ajustes basicos

fecha de creacion 8-jul-2026
- en el modal que esta en la vista de clientes http://127.0.0.1:8000/client/customers/332 agregar los cambios que ya se hicieron al modelo aninal, separar notas de alergias y agregar la expresion regular para revisar que el chiop sea de 15 numeros (solo numeros,) agregar alguna nota ahi abajo para que el user sepa que solo se aceptan 15 numeros

- si el guardado falla por alguna razon mantener los datos para no tener que volver a capturar , hice una prueba el guardado fallo porquea uno no existe el regex que revise live lo que esta haciendo el user, 

- al crear una nota hay un card que presenta totales, http://127.0.0.1:8000/client/ventas/crear?customer_id=332 vamos a agregar el numero de servicios que lleva la nota 

- al guardar la nota nos lleva a http://127.0.0.1:8000/client/ventas/224 lo cual esta bien, pero si en esa pantalla le damos volver nos regresa a notas, nos deberia regresar al cliente con el que estabamos trbajando agregando notas 

-  en http://127.0.0.1:8000/client/customers/332 en la pestaña de las notas,cuando eliminamos una la pagina redirecciona a 