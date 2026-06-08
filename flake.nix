{
  inputs = {
    php-dev.url = "github:MikeGeorgeff/php-dev-flake";
  };

  outputs = { self, php-dev }:
  {
    devShells = php-dev.devShells;
  };
}
