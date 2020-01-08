
# Introdução

    este documento tem como objetivo descrever os recursos de acesso a dados 
    presentes na plataforma Uebster para os desenvolvedores front-end.
***

## Dados Privados, consulta REST
    estes dados são definidos como privados pelo administrador (padrão), 
    podendo apenas ser acessados informando uma KEY (chave de acessso), 
    definida e fornecida pelo administrador.
    
> As consultas privadas seguem o mesmo modelo das consultas Públicas, exceto que:

>utilizam a rota: **DOMINIO**/api/... em vez de **DOMINIO**/app/...

>todas as requests são do tipo POST

>todas as requests exigem o envio da variável KEY no corpo da requisição POST, com o valor da chave fornecida pelo administrador.
  
## Dados Públicos, consulta REST
    estes dados são definidos como de acesso público pelo administrador, 
    podendo qualquer pessoa ter acesso aos registros para manipulá-lo.

# # LEITURA
> **GET REQUEST**

    retorna os dados de uma entidade. Por padrão retornará todos os dados, mas pode ser delimitado a quantidade.

**DOMINIO**/app/get/**{entidade}**/**{limit}**/**{offset}**

>LEGENDA
* DOMINIO => esta constante representa o domínio do aplicativo. (ex: var DOMINIO = "http://uebster.com";)
* {entidade} => (string) esta variável pode ser qualquer entidade do sistema.
* {limit} => (number) valor opcional que determina limite máximo de registros.
* {offset} => (number) valor opcional, que se usado, requer a definição do {limit} e ignora _X_ primeiros registros.

>EXEMPLOS APLICÁVEIS
   
    https://ag3tecnologia.com.br/app/get/clientes (obtém todos os clientes)
    https://ag3tecnologia.com.br/app/get/trabalhos/20 (obtém os 20 últimos trabalhos)
    https://ag3tecnologia.com.br/app/get/depoimentos/10/5 (obtém os 10 últimos depoimentos, ignorando os 5 primeiros registros)
   
# # BUSCA / PESQUISA
> **GET REQUEST**

    retorna os dados de uma entidade que coincedirem com a busca.

**DOMINIO**/app/search/**{entidade}**/**{campo}**/**{valor}**

>LEGENDA
* DOMINIO => esta constante representa o domínio do aplicativo. (ex: var DOMINIO = "http://uebster.com";)
* {entidade} => (string) esta variável pode ser qualquer entidade do sistema.
* {campo} => (string) campo no qual será feito a busca pelo valor.
* {valor} => (string) valor a ser buscado. Os valor será considerado na busca mesmo que este seja encontrado parcialmente.

>EXEMPLOS APLICÁVEIS
   
    https://ag3tecnologia.com.br/app/search/clientes/nome/marcos (obtém todos os clientes que possuem marcos no seu nome)
    https://ag3tecnologia.com.br/app/search/trabalhos/status/1 (obtém todos os trabalhos com status igual a 1)
    
# # ESCRITA / CRIAÇÃO
> **POST REQUEST**
    
    cria dados em uma entidade. Retorna erros caso exista ou retorna o registro criado caso seja um sucesso.

**DOMINIO**/app/put/**{entidade}**

>LEGENDA
* DOMINIO => esta constante representa o domínio do aplicativo. (ex: var DOMINIO = "http://uebster.com";)
* {entidade} => (string) esta variável pode ser qualquer entidade do sistema.

>EXEMPLOS APLICÁVEIS
   
    https://ag3tecnologia.com.br/app/put/clientes (cria novo registro em cliente)
    POST DATA: {nome: "marcos", idade: 28, funcao: "Desenvolvedor"}
    
# # ATUALIZAÇÃO
> **POST REQUEST**
    
    atualiza os dados em uma entidade. Retorna erros caso exista ou retorna o registro atualizado caso seja um sucesso.
    É obrigatório passar o parâmetro 'id' no corpo do request junto com as demais informações a serem atualizadas,
    o 'id' determinará o registro a ser atualizado, caso ele seja omitido, uma criação será executada.

**DOMINIO**/app/put/**{entidade}**

>LEGENDA
* DOMINIO => esta constante representa o domínio do aplicativo. (ex: var DOMINIO = "http://uebster.com";)
* {entidade} => (string) esta variável pode ser qualquer entidade do sistema.

>EXEMPLOS APLICÁVEIS
   
    https://ag3tecnologia.com.br/app/put/clientes (cria novo registro em cliente)
    POST DATA: {id: 1, nome: "marcos", idade: 28, funcao: "Desenvolvedor"}


***
***
***
>: )
***
# # indexedDb
    toda aplicação Uebster por padrão é um PWA, cujo qual utiliza o indexedDb para 
    armazenar e controlar os dados das entidades diretamente do front-end. 
    Diferente das requisições REST, as consultas ao indexedDb levam em consideração
    as permissões do usuário que esta acessando/executando o comando, retornando 
    apenas dados que o usuário tem acesso/permissão.
    
> Toda a função de acesso ao indexedDb retorna uma '_Promise_'

## # LEITURA
    //ler todos os dados de uma entidade
    db.exeRead({entidade}).then(resultados => {
        //faça algo com os 'resultados' aqui
    });
    
    //ler registro específico (id = 1)
    db.exeRead({entidade}, 1).then(resultado => {
        //faça algo com o 'resultado' aqui
    });
    
> LEGENDA

* {entidade} => variável representando uma entidade qualquer.
 
## # ESCRITA / CRIAÇÃO
    //cria um registro na entidade
    db.exeCreate({entidade}, {nome: 'nome qualquer', idade: 17}).then(resultado => {
        //consulte o resultado para verificar se existe erros ou se foi criado com sucesso.
    });
    
> LEGENDA

* {entidade} => variável representando uma entidade qualquer.

## # ATUALIZAÇÃO
    //atualiza um registro na entidade (ex: id = 1)
    db.exeUpdate({entidade}, {nome: 'nome qualquer', idade: 17}, 1).then(resultado => {
        //consulte o resultado para verificar se existe erros ou se foi atualizado com sucesso.
    });
    
> LEGENDA

* {entidade} => variável representando uma entidade qualquer.
## # EXCLUSÃO
    //deleta um registro na entidade (ex: id = 1)
    db.exeDelete({entidade}, 1).then(() => {
        //faça algo depois de excluir aqui.
    });
    
> LEGENDA

* {entidade} => variável representando uma entidade qualquer.