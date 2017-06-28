# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|

    config.vm.define :secretsanta do |secretsanta_config|

        secretsanta_config.vm.box = "Intracto/openbsd-6.1-amd64"

        secretsanta_config.vm.provider "virtualbox" do |v|
            # show a display for easy debugging
            v.gui = false

            # RAM size
            v.memory = 2048

            # Allow symlinks on the shared folder
            v.customize ["setextradata", :id, "VBoxInternal2/SharedFoldersEnableSymlinksCreate/v-root", "1"]
        end

        secretsanta_config.vm.synced_folder ".", "/vagrant", type: "nfs"
        secretsanta_config.vm.network "private_network", ip: "192.168.33.50"

        # Shell provisioning
        #secretsanta_config.vm.provision :shell, :path => "shell_provisioner/run.sh"

        secretsanta_config.vm.provision "ansible" do |ansible|
            #ansible.verbose = "v"
            ansible.playbook = "provisioning/dev/playbook.yml"
        end

    end

end

